<?php
// upload.php - Untuk Zeabur dengan LOG LENGKAP

// Aktifkan error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set timezone
date_default_timezone_set('Asia/Jakarta');

// Fungsi logging
function writeLog($message, $data = null) {
    $log_file = __DIR__ . '/upload_debug.log';
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[$timestamp] $message";
    
    if ($data !== null) {
        $log_message .= " | Data: " . print_r($data, true);
    }
    
    file_put_contents($log_file, $log_message . "\n", FILE_APPEND);
    
    // Juga kirim ke error log PHP
    error_log($log_message);
}

// Log semua request
writeLog("=== NEW REQUEST ===");
writeLog("REQUEST_METHOD: " . $_SERVER['REQUEST_METHOD']);
writeLog("REQUEST_URI: " . $_SERVER['REQUEST_URI']);
writeLog("QUERY_STRING: " . ($_SERVER['QUERY_STRING'] ?? 'none'));

// Header untuk CORS
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS, DELETE');
header('Access-Control-Allow-Headers: *');

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    writeLog("OPTIONS request - sending 200");
    http_response_code(200);
    exit();
}

// ========== AUTO CREATE FOLDER UPLOADS ==========
$upload_dir = __DIR__ . '/uploads/';

// Cek dan buat folder dengan detail
writeLog("Checking upload directory: " . $upload_dir);

if (!file_exists($upload_dir)) {
    writeLog("Folder uploads TIDAK ADA, mencoba membuat...");
    
    // Coba buat folder
    if (mkdir($upload_dir, 0777, true)) {
        writeLog("SUKSES: Folder uploads berhasil dibuat");
        
        // Coba buat file .htaccess untuk keamanan
        $htaccess = $upload_dir . '.htaccess';
        file_put_contents($htaccess, "Options -Indexes\n<FilesMatch \"\.(php|php3|php4|php5|phtml|inc)$\">\n    Order Deny,Allow\n    Deny from all\n</FilesMatch>");
        writeLog("File .htaccess dibuat di folder uploads");
        
    } else {
        $error = error_get_last();
        writeLog("GAGAL: Tidak bisa membuat folder uploads", $error);
    }
} else {
    writeLog("Folder uploads SUDAH ADA");
    
    // Cek permission
    $perms = fileperms($upload_dir);
    $perms_string = substr(sprintf('%o', $perms), -4);
    writeLog("Permission folder: " . $perms_string);
    
    // Cek writable
    if (is_writable($upload_dir)) {
        writeLog("Folder uploads BISA ditulis");
    } else {
        writeLog("Folder uploads TIDAK BISA ditulis - coba chmod...");
        chmod($upload_dir, 0777);
    }
}

// Cek free space
$free_space = disk_free_space(__DIR__);
if ($free_space !== false) {
    $free_space_mb = round($free_space / 1024 / 1024, 2);
    writeLog("Free space: {$free_space_mb} MB");
}

// Fungsi untuk membersihkan nama file
function cleanFilename($string) {
    $string = preg_replace('/[^a-zA-Z0-9]/', '_', $string);
    return substr($string, 0, 50);
}

// Handle request
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    writeLog("=== POST REQUEST ===");
    
    // Log semua POST data
    writeLog("POST data: ", $_POST);
    writeLog("FILES data: ", $_FILES);
    
    // Cek file
    if (!isset($_FILES['file'])) {
        writeLog("ERROR: Tidak ada file dalam request");
        echo json_encode(['success' => false, 'error' => 'Tidak ada file']);
        exit;
    }
    
    $file = $_FILES['file'];
    $title = isset($_POST['title']) ? $_POST['title'] : 'Foto Pengunjung';
    
    writeLog("File details:", [
        'name' => $file['name'],
        'type' => $file['type'],
        'size' => $file['size'],
        'tmp_name' => $file['tmp_name'],
        'error' => $file['error']
    ]);
    
    // Validasi file
    $allowed_types = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif'];
    $max_size = 5 * 1024 * 1024; // 5MB
    
    if (!in_array($file['type'], $allowed_types)) {
        writeLog("ERROR: Tipe file tidak diizinkan: " . $file['type']);
        echo json_encode(['success' => false, 'error' => 'Tipe file tidak didukung. Hanya JPG, PNG, GIF']);
        exit;
    }
    
    if ($file['size'] > $max_size) {
        writeLog("ERROR: File terlalu besar: " . $file['size'] . " bytes");
        echo json_encode(['success' => false, 'error' => 'File terlalu besar. Maksimal 5MB']);
        exit;
    }
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $upload_errors = [
            UPLOAD_ERR_INI_SIZE => 'File melebihi upload_max_filesize',
            UPLOAD_ERR_FORM_SIZE => 'File melebihi MAX_FILE_SIZE',
            UPLOAD_ERR_PARTIAL => 'File hanya terupload sebagian',
            UPLOAD_ERR_NO_FILE => 'Tidak ada file',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
        ];
        $error_msg = $upload_errors[$file['error']] ?? 'Unknown upload error';
        writeLog("ERROR: Upload error: " . $error_msg);
        echo json_encode(['success' => false, 'error' => $error_msg]);
        exit;
    }
    
    // Generate nama file unik
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $clean_title = cleanFilename($title);
    $filename = time() . '_' . $clean_title . '.' . $extension;
    $target_file = $upload_dir . $filename;
    
    writeLog("Target file: " . $target_file);
    
    // Pindah file
    if (move_uploaded_file($file['tmp_name'], $target_file)) {
        writeLog("SUKSES: File berhasil dipindahkan ke: " . $target_file);
        
        // Cek file size setelah upload
        if (file_exists($target_file)) {
            $file_size = filesize($target_file);
            writeLog("File size setelah upload: " . $file_size . " bytes");
        }
        
        // Dapatkan URL lengkap
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'];
        $base_url = $protocol . $host . '/';
        
        // Cek apakah file bisa diakses via URL
        $file_url = $base_url . 'uploads/' . $filename;
        writeLog("File URL: " . $file_url);
        
        echo json_encode([
            'success' => true,
            'id' => time(),
            'url' => $file_url,
            'filename' => $filename,
            'title' => $title,
            'size' => $file['size']
        ]);
    } else {
        $error = error_get_last();
        writeLog("ERROR: Gagal move_uploaded_file", $error);
        echo json_encode(['success' => false, 'error' => 'Gagal menyimpan file: ' . ($error['message'] ?? 'unknown error')]);
    }
    exit;
}

if ($method === 'GET') {
    writeLog("=== GET REQUEST ===");
    
    $action = isset($_GET['action']) ? $_GET['action'] : '';
    writeLog("Action: " . $action);
    
    if ($action === 'getPhotos') {
        writeLog("Getting photos list...");
        
        // Cek apakah folder ada
        if (!file_exists($upload_dir)) {
            writeLog("Folder uploads tidak ada, return empty array");
            echo json_encode([]);
            exit;
        }
        
        // Scan folder
        $files = glob($upload_dir . "*.{jpg,jpeg,png,gif,JPG,JPEG,PNG,GIF}", GLOB_BRACE);
        writeLog("Found " . count($files) . " files");
        
        $photos = [];
        
        foreach ($files as $file) {
            $filename = basename($file);
            $timestamp = filemtime($file);
            $file_size = filesize($file);
            
            // Parse title dari filename
            $title_parts = explode('_', $filename);
            $title = isset($title_parts[1]) ? pathinfo($title_parts[1], PATHINFO_FILENAME) : 'Foto';
            
            // Dapatkan URL lengkap
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
            $host = $_SERVER['HTTP_HOST'];
            $base_url = $protocol . $host . '/';
            
            $photos[] = [
                'id' => $timestamp,
                'title' => str_replace('_', ' ', $title),
                'url' => $base_url . 'uploads/' . $filename,
                'filename' => $filename,
                'size' => $file_size,
                'timestamp' => date('Y-m-d H:i:s', $timestamp)
            ];
        }
        
        // Urutkan dari terbaru
        usort($photos, function($a, $b) {
            return strtotime($b['timestamp']) - strtotime($a['timestamp']);
        });
        
        writeLog("Returning " . count($photos) . " photos");
        echo json_encode($photos);
        exit;
    }
    
    if ($action === 'delete') {
        writeLog("=== DELETE REQUEST ===");
        
        $filename = isset($_GET['filename']) ? $_GET['filename'] : '';
        writeLog("Filename to delete: " . $filename);
        
        if (empty($filename)) {
            writeLog("ERROR: No filename provided");
            echo json_encode(['success' => false, 'error' => 'Filename tidak valid']);
            exit;
        }
        
        $filepath = $upload_dir . $filename;
        writeLog("Full path: " . $filepath);
        
        if (!file_exists($filepath)) {
            writeLog("ERROR: File not found");
            echo json_encode(['success' => false, 'error' => 'File tidak ditemukan']);
            exit;
        }
        
        if (unlink($filepath)) {
            writeLog("SUKSES: File deleted");
            echo json_encode(['success' => true]);
        } else {
            $error = error_get_last();
            writeLog("ERROR: Failed to delete file", $error);
            echo json_encode(['success' => false, 'error' => 'Gagal menghapus file']);
        }
        exit;
    }
    
    if ($action === 'rename') {
        writeLog("=== RENAME REQUEST ===");
        
        $oldname = isset($_GET['oldname']) ? $_GET['oldname'] : '';
        $newtitle = isset($_GET['newtitle']) ? $_GET['newtitle'] : '';
        
        writeLog("Old name: " . $oldname);
        writeLog("New title: " . $newtitle);
        
        if (empty($oldname) || empty($newtitle)) {
            writeLog("ERROR: Incomplete data");
            echo json_encode(['success' => false, 'error' => 'Data tidak lengkap']);
            exit;
        }
        
        $oldpath = $upload_dir . $oldname;
        writeLog("Old path: " . $oldpath);
        
        if (!file_exists($oldpath)) {
            writeLog("ERROR: File not found");
            echo json_encode(['success' => false, 'error' => 'File tidak ditemukan']);
            exit;
        }
        
        // Buat nama file baru
        $extension = pathinfo($oldname, PATHINFO_EXTENSION);
        $clean_title = cleanFilename($newtitle);
        $timestamp = filemtime($oldpath);
        $newname = $timestamp . '_' . $clean_title . '.' . $extension;
        $newpath = $upload_dir . $newname;
        
        writeLog("New name: " . $newname);
        writeLog("New path: " . $newpath);
        
        if (rename($oldpath, $newpath)) {
            writeLog("SUKSES: File renamed");
            
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
            $host = $_SERVER['HTTP_HOST'];
            $base_url = $protocol . $host . '/';
            
            echo json_encode([
                'success' => true,
                'newFilename' => $newname,
                'newUrl' => $base_url . 'uploads/' . $newname
            ]);
        } else {
            $error = error_get_last();
            writeLog("ERROR: Failed to rename", $error);
            echo json_encode(['success' => false, 'error' => 'Gagal rename file']);
        }
        exit;
    }
}

// Default response
writeLog("ERROR: Invalid request - no action specified");
echo json_encode(['success' => false, 'error' => 'Invalid request']);
?>
