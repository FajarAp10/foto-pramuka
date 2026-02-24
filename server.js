// server.js
const express = require('express');
const multer = require('multer');
const path = require('path');
const fs = require('fs');
const cors = require('cors');

const app = express();
const PORT = process.env.PORT || 3000;

// ========== SETUP LOGGING ==========
const logFile = path.join(__dirname, 'server.log');

function writeLog(message, data = null) {
    const timestamp = new Date().toISOString();
    let logMessage = `[${timestamp}] ${message}`;
    
    if (data) {
        logMessage += `\n${JSON.stringify(data, null, 2)}`;
    }
    
    console.log(logMessage);
    fs.appendFileSync(logFile, logMessage + '\n');
}

// Buat folder logs kalau belum ada
if (!fs.existsSync('logs')) {
    fs.mkdirSync('logs');
}

// Middleware
app.use(cors());
app.use(express.json());
app.use('/uploads', express.static('uploads'));

// Log semua request
app.use((req, res, next) => {
    writeLog(`${req.method} ${req.url} - IP: ${req.ip}`);
    next();
});

// ========== SETUP UPLOAD ==========
// Pastikan folder uploads ada
const uploadDir = path.join(__dirname, 'uploads');
if (!fs.existsSync(uploadDir)) {
    fs.mkdirSync(uploadDir, { recursive: true });
    writeLog('📁 Folder uploads dibuat');
}

// Konfigurasi storage dengan nama file yang benar
const storage = multer.diskStorage({
    destination: function (req, file, cb) {
        cb(null, uploadDir);
    },
    filename: function (req, file, cb) {
        // Ambil title dari form data
        const title = req.body.title || 'Foto Pengunjung';
        
        // Bersihkan title dari karakter aneh
        const cleanTitle = title
            .replace(/[^a-zA-Z0-9\s]/g, '') // Hanya huruf, angka, spasi
            .replace(/\s+/g, '_') // Ganti spasi dengan underscore
            .substring(0, 50); // Maksimal 50 karakter
        
        const ext = path.extname(file.originalname);
        const timestamp = Date.now();
        
        // Format: timestamp_nama_file_asli.ext
        const filename = `${timestamp}_${cleanTitle}${ext}`;
        
        writeLog(`📸 File akan disimpan sebagai: ${filename}`);
        cb(null, filename);
    }
});

// Filter file
const fileFilter = (req, file, cb) => {
    const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
    
    if (allowedTypes.includes(file.mimetype)) {
        cb(null, true);
    } else {
        cb(new Error('Tipe file tidak didukung. Hanya JPG, PNG, GIF'), false);
    }
};

const upload = multer({
    storage: storage,
    limits: { fileSize: 5 * 1024 * 1024 }, // 5MB
    fileFilter: fileFilter
});

// ========== API ENDPOINTS ==========

// Test API
app.get('/', (req, res) => {
    writeLog('✅ API diakses');
    res.json({ 
        status: 'OK', 
        message: 'API Galeri Pramuka berjalan',
        time: new Date().toISOString(),
        endpoints: {
            upload: 'POST /upload',
            photos: 'GET /photos',
            delete: 'DELETE /photos/:filename',
            rename: 'PUT /photos/:filename'
        }
    });
});

// Upload foto
app.post('/upload', upload.single('file'), (req, res) => {
    try {
        writeLog('📤 UPLOAD - Request received');
        
        if (!req.file) {
            writeLog('❌ UPLOAD - No file');
            return res.status(400).json({ 
                success: false, 
                error: 'Tidak ada file' 
            });
        }

        const title = req.body.title || 'Foto Pengunjung';
        const baseUrl = `${req.protocol}://${req.get('host')}`;
        
        writeLog('✅ UPLOAD - Success', {
            filename: req.file.filename,
            title: title,
            size: req.file.size,
            url: `${baseUrl}/uploads/${req.file.filename}`
        });
        
        res.json({
            success: true,
            id: Date.now(),
            url: `${baseUrl}/uploads/${req.file.filename}`,
            filename: req.file.filename,
            title: title,
            size: req.file.size
        });

    } catch (error) {
        writeLog('❌ UPLOAD - Error: ' + error.message);
        res.status(500).json({ 
            success: false, 
            error: error.message 
        });
    }
});

// Get semua foto
app.get('/photos', (req, res) => {
    try {
        writeLog('📸 GET PHOTOS - Request received');
        
        if (!fs.existsSync(uploadDir)) {
            writeLog('📸 GET PHOTOS - No uploads folder');
            return res.json([]);
        }

        const files = fs.readdirSync(uploadDir);
        writeLog(`📸 GET PHOTOS - Found ${files.length} files`);
        
        const photos = files
            .filter(file => {
                const ext = path.extname(file).toLowerCase();
                return ['.jpg', '.jpeg', '.png', '.gif'].includes(ext);
            })
            .map(file => {
                const filePath = path.join(uploadDir, file);
                const stats = fs.statSync(filePath);
                const baseUrl = `${req.protocol}://${req.get('host')}`;
                
                // Parse title dari filename (format: timestamp_title.ext)
                let title = file;
                if (file.includes('_')) {
                    const parts = file.split('_');
                    if (parts.length > 1) {
                        title = parts.slice(1).join('_').replace(/\.[^/.]+$/, "");
                    }
                } else {
                    title = file.replace(/\.[^/.]+$/, "");
                }
                
                return {
                    id: stats.birthtimeMs,
                    title: title.replace(/_/g, ' '), // Ganti underscore dengan spasi
                    url: `${baseUrl}/uploads/${file}`,
                    filename: file,
                    size: stats.size,
                    timestamp: stats.birthtime,
                    createdAt: stats.birthtime.toISOString()
                };
            })
            .sort((a, b) => b.timestamp - a.timestamp); // Urutkan dari terbaru

        writeLog(`📸 GET PHOTOS - Returning ${photos.length} photos`);
        res.json(photos);

    } catch (error) {
        writeLog('❌ GET PHOTOS - Error: ' + error.message);
        res.status(500).json({ 
            success: false, 
            error: error.message 
        });
    }
});

// Hapus foto
app.delete('/photos/:filename', (req, res) => {
    try {
        const filename = req.params.filename;
        writeLog(`🗑️ DELETE - Request for: ${filename}`);
        
        const filePath = path.join(uploadDir, filename);

        if (!fs.existsSync(filePath)) {
            writeLog(`❌ DELETE - File not found: ${filename}`);
            return res.status(404).json({ 
                success: false, 
                error: 'File tidak ditemukan' 
            });
        }

        fs.unlinkSync(filePath);
        writeLog(`✅ DELETE - Success: ${filename}`);
        res.json({ success: true });

    } catch (error) {
        writeLog('❌ DELETE - Error: ' + error.message);
        res.status(500).json({ 
            success: false, 
            error: error.message 
        });
    }
});

// Rename foto
app.put('/photos/:filename', express.json(), (req, res) => {
    try {
        const oldFilename = req.params.filename;
        const { newTitle } = req.body;
        
        writeLog(`✏️ RENAME - Request for: ${oldFilename} -> ${newTitle}`);
        
        if (!newTitle) {
            writeLog('❌ RENAME - No new title');
            return res.status(400).json({ 
                success: false, 
                error: 'Title baru diperlukan' 
            });
        }

        const oldPath = path.join(uploadDir, oldFilename);
        
        if (!fs.existsSync(oldPath)) {
            writeLog(`❌ RENAME - File not found: ${oldFilename}`);
            return res.status(404).json({ 
                success: false, 
                error: 'File tidak ditemukan' 
            });
        }

        // Buat nama file baru
        const ext = path.extname(oldFilename);
        const cleanTitle = newTitle
            .replace(/[^a-zA-Z0-9\s]/g, '')
            .replace(/\s+/g, '_')
            .substring(0, 50);
        
        const timestamp = Date.now();
        const newFilename = `${timestamp}_${cleanTitle}${ext}`;
        const newPath = path.join(uploadDir, newFilename);

        // Rename file
        fs.renameSync(oldPath, newPath);
        
        const baseUrl = `${req.protocol}://${req.get('host')}`;
        
        writeLog(`✅ RENAME - Success: ${oldFilename} -> ${newFilename}`);

        res.json({
            success: true,
            newFilename: newFilename,
            newUrl: `${baseUrl}/uploads/${newFilename}`
        });

    } catch (error) {
        writeLog('❌ RENAME - Error: ' + error.message);
        res.status(500).json({ 
            success: false, 
            error: error.message 
        });
    }
});

// Error handler
app.use((err, req, res, next) => {
    writeLog('❌ ERROR - ' + err.message);
    
    if (err instanceof multer.MulterError) {
        if (err.code === 'LIMIT_FILE_SIZE') {
            return res.status(400).json({ 
                success: false, 
                error: 'File terlalu besar. Maksimal 5MB' 
            });
        }
    }
    
    res.status(500).json({ 
        success: false, 
        error: err.message 
    });
});

// Start server
app.listen(PORT, () => {
    writeLog(`🚀 Server started on port ${PORT}`);
    writeLog(`📁 Upload folder: ${uploadDir}`);
    writeLog(`📝 Log file: ${logFile}`);
});

// Log server status setiap jam
setInterval(() => {
    const files = fs.readdirSync(uploadDir).length;
    writeLog(`📊 STATUS - Total files in uploads: ${files}`);
}, 60 * 60 * 1000); // Setiap 1 jam