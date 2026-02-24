<?php
// upload.php - Untuk Zeabur

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Biar bisa diakses dari Netlify
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: *');

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Buat folder uploads jika belum ada
$upload_dir = 'uploads/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Fungsi untuk membersihkan nama file
function cleanFilename($string) {
    $string = preg_replace('/[^a-zA-Z0-9]/', '_', $string);
    return substr($string, 0, 50);
}

// Handle request
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    // UPLOAD FOTO
    if (!isset($_FILES['file'])) {
        echo json_encode(['success' => false, 'error' => 'Tidak ada file']);
        exit;
    }
    
    $file = $_FILES['file'];
    $title = isset($_POST['title']) ? $_POST['title'] : 'Foto Pengunjung';
    
    // Validasi file
    $allowed_types = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif'];
    $max_size = 5 * 1024 * 1024; // 5MB
    
    if (!in_array($file['type'], $allowed_types)) {
        echo json_encode(['success' => false, 'error' => 'Tipe file tidak didukung. Hanya JPG, PNG, GIF']);
        exit;
    }
    
    if ($file['size'] > $max_size) {
        echo json_encode(['success' => false, 'error' => 'File terlalu besar. Maksimal 5MB']);
        exit;
    }
    
    // Generate nama file unik
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $clean_title = cleanFilename($title);
    $filename = time() . '_' . $clean_title . '.' . $extension;
    $target_file = $upload_dir . $filename;
    
    // Pindah file
    if (move_uploaded_file($file['tmp_name'], $target_file)) {
        // Dapatkan URL lengkap
        $protocol = isset($_SERVER['HTTPS']) ? 'https://' : 'http://';
        $base_url = $protocol . $_SERVER['HTTP_HOST'] . '/';
        
        echo json_encode([
            'success' => true,
            'id' => time(),
            'url' => $base_url . $target_file,
            'filename' => $filename,
            'title' => $title
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Gagal menyimpan file']);
    }
    exit;
}

if ($method === 'GET') {
    $action = isset($_GET['action']) ? $_GET['action'] : '';
    
    if ($action === 'getPhotos') {
        // AMBIL DAFTAR FOTO
        $files = glob($upload_dir . "*.{jpg,jpeg,png,gif,JPG,JPEG,PNG,GIF}", GLOB_BRACE);
        $photos = [];
        
        foreach ($files as $file) {
            $filename = basename($file);
            $timestamp = filemtime($file);
            $title_parts = explode('_', $filename);
            $title = isset($title_parts[1]) ? pathinfo($title_parts[1], PATHINFO_FILENAME) : 'Foto';
            
            // Dapatkan URL lengkap
            $protocol = isset($_SERVER['HTTPS']) ? 'https://' : 'http://';
            $base_url = $protocol . $_SERVER['HTTP_HOST'] . '/';
            
            $photos[] = [
                'id' => $timestamp,
                'title' => str_replace('_', ' ', $title),
                'url' => $base_url . $file,
                'filename' => $filename,
                'timestamp' => date('Y-m-d H:i:s', $timestamp)
            ];
        }
        
        // Urutkan dari terbaru
        usort($photos, function($a, $b) {
            return strtotime($b['timestamp']) - strtotime($a['timestamp']);
        });
        
        echo json_encode($photos);
        exit;
    }
    
    if ($action === 'delete') {
        // HAPUS FOTO
        $filename = isset($_GET['filename']) ? $_GET['filename'] : '';
        
        if (empty($filename)) {
            echo json_encode(['success' => false, 'error' => 'Filename tidak valid']);
            exit;
        }
        
        $filepath = $upload_dir . $filename;
        
        if (file_exists($filepath)) {
            if (unlink($filepath)) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Gagal menghapus file']);
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'File tidak ditemukan']);
        }
        exit;
    }
    
    if ($action === 'rename') {
        // RENAME FOTO
        $oldname = isset($_GET['oldname']) ? $_GET['oldname'] : '';
        $newtitle = isset($_GET['newtitle']) ? $_GET['newtitle'] : '';
        
        if (empty($oldname) || empty($newtitle)) {
            echo json_encode(['success' => false, 'error' => 'Data tidak lengkap']);
            exit;
        }
        
        $oldpath = $upload_dir . $oldname;
        
        if (!file_exists($oldpath)) {
            echo json_encode(['success' => false, 'error' => 'File tidak ditemukan']);
            exit;
        }
        
        // Buat nama file baru
        $extension = pathinfo($oldname, PATHINFO_EXTENSION);
        $clean_title = cleanFilename($newtitle);
        $timestamp = filemtime($oldpath);
        $newname = $timestamp . '_' . $clean_title . '.' . $extension;
        $newpath = $upload_dir . $newname;
        
        if (rename($oldpath, $newpath)) {
            // Dapatkan URL lengkap
            $protocol = isset($_SERVER['HTTPS']) ? 'https://' : 'http://';
            $base_url = $protocol . $_SERVER['HTTP_HOST'] . '/';
            
            echo json_encode([
                'success' => true,
                'newFilename' => $newname,
                'newUrl' => $base_url . $newpath
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Gagal rename file']);
        }
        exit;
    }
}

// Default response
echo json_encode(['success' => false, 'error' => 'Invalid request']);
?>