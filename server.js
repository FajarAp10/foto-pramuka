// server.js
const express = require('express');
const multer = require('multer');
const path = require('path');
const fs = require('fs');
const cors = require('cors');

const app = express();
const PORT = process.env.PORT || 3000;

// Middleware
app.use(cors());
app.use(express.json());
app.use('/uploads', express.static('uploads'));

// Setup multer untuk upload file
const storage = multer.diskStorage({
    destination: function (req, file, cb) {
        const dir = './uploads';
        if (!fs.existsSync(dir)) {
            fs.mkdirSync(dir, { recursive: true });
        }
        cb(null, dir);
    },
    filename: function (req, file, cb) {
        const uniqueSuffix = Date.now() + '-' + Math.round(Math.random() * 1E9);
        const ext = path.extname(file.originalname);
        cb(null, uniqueSuffix + ext);
    }
});

const upload = multer({ 
    storage: storage,
    limits: { fileSize: 5 * 1024 * 1024 }, // 5MB
    fileFilter: (req, file, cb) => {
        const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        if (allowedTypes.includes(file.mimetype)) {
            cb(null, true);
        } else {
            cb(new Error('Tipe file tidak didukung'));
        }
    }
});

// ========== API ENDPOINTS ==========

// Test API
app.get('/', (req, res) => {
    res.json({ 
        status: 'OK', 
        message: 'API Galeri Pramuka berjalan',
        time: new Date().toISOString()
    });
});

// Upload foto
app.post('/upload', upload.single('file'), (req, res) => {
    try {
        if (!req.file) {
            return res.status(400).json({ 
                success: false, 
                error: 'Tidak ada file' 
            });
        }

        const title = req.body.title || 'Foto Pengunjung';
        const baseUrl = `${req.protocol}://${req.get('host')}`;
        
        res.json({
            success: true,
            id: Date.now(),
            url: `${baseUrl}/uploads/${req.file.filename}`,
            filename: req.file.filename,
            title: title,
            size: req.file.size
        });

    } catch (error) {
        res.status(500).json({ 
            success: false, 
            error: error.message 
        });
    }
});

// Get semua foto
app.get('/photos', (req, res) => {
    try {
        const uploadDir = './uploads';
        if (!fs.existsSync(uploadDir)) {
            return res.json([]);
        }

        const files = fs.readdirSync(uploadDir);
        const photos = files
            .filter(file => {
                const ext = path.extname(file).toLowerCase();
                return ['.jpg', '.jpeg', '.png', '.gif'].includes(ext);
            })
            .map(file => {
                const filePath = path.join(uploadDir, file);
                const stats = fs.statSync(filePath);
                const baseUrl = `${req.protocol}://${req.get('host')}`;
                
                // Parse title dari filename (optional)
                const title = file.split('-')[1] || file;
                
                return {
                    id: stats.birthtimeMs,
                    title: title.replace(/\.[^/.]+$/, ""),
                    url: `${baseUrl}/uploads/${file}`,
                    filename: file,
                    size: stats.size,
                    timestamp: stats.birthtime
                };
            })
            .sort((a, b) => b.timestamp - a.timestamp); // Urutkan dari terbaru

        res.json(photos);

    } catch (error) {
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
        const filePath = path.join('./uploads', filename);

        if (!fs.existsSync(filePath)) {
            return res.status(404).json({ 
                success: false, 
                error: 'File tidak ditemukan' 
            });
        }

        fs.unlinkSync(filePath);
        res.json({ success: true });

    } catch (error) {
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
        
        if (!newTitle) {
            return res.status(400).json({ 
                success: false, 
                error: 'Title baru diperlukan' 
            });
        }

        const oldPath = path.join('./uploads', oldFilename);
        
        if (!fs.existsSync(oldPath)) {
            return res.status(404).json({ 
                success: false, 
                error: 'File tidak ditemukan' 
            });
        }

        // Buat nama file baru
        const ext = path.extname(oldFilename);
        const cleanTitle = newTitle.replace(/[^a-zA-Z0-9]/g, '_');
        const timestamp = Date.now();
        const newFilename = `${timestamp}-${cleanTitle}${ext}`;
        const newPath = path.join('./uploads', newFilename);

        // Rename file
        fs.renameSync(oldPath, newPath);
        
        const baseUrl = `${req.protocol}://${req.get('host')}`;

        res.json({
            success: true,
            newFilename: newFilename,
            newUrl: `${baseUrl}/uploads/${newFilename}`
        });

    } catch (error) {
        res.status(500).json({ 
            success: false, 
            error: error.message 
        });
    }
});

// Error handler
app.use((err, req, res, next) => {
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
    console.log(`Server berjalan di port ${PORT}`);
    console.log(`Upload endpoint: http://localhost:${PORT}/upload`);
    console.log(`Photos endpoint: http://localhost:${PORT}/photos`);
});