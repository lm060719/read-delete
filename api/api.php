<?php
// 设置响应头
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// 数据库配置
$host = 'localhost';
$dbname = 'burn_user';  // burn_user替换你的数据库名
$username = 'burn_user';  // burn_user替换你的用户名
$password = 'burn_user';  // burn_user替换你的密码

// 连接数据库 - 移除字符集参数，使用默认设置
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password, array(
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"
    ));
} catch(PDOException $e) {
    die(json_encode(['success' => false, 'message' => '数据库连接失败: ' . $e->getMessage()]));
}

// 生成唯一ID
function generateId() {
    return substr(md5(uniqid(mt_rand(), true)), 0, 32);
}

// 自动清理过期数据
function autoCleanup($pdo) {
    try {
        $stmt = $pdo->prepare("DELETE FROM messages WHERE expires_at < NOW()");
        $stmt->execute();
        // 可选：记录清理日志
        // error_log("自动清理执行，清理了 " . $stmt->rowCount() . " 条过期记录");
    } catch(Exception $e) {
        error_log("自动清理失败: " . $e->getMessage());
    }
}

// 执行清理
autoCleanup($pdo);

// 获取POST数据
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['action'])) {
    echo json_encode(['success' => false, 'message' => '参数不完整']);
    exit;
}

$action = $input['action'];

if ($action === 'create') {
    // 创建焚化信息
    if (!isset($input['message']) || !isset($input['burn_time']) || !isset($input['max_views'])) {
        echo json_encode(['success' => false, 'message' => '参数不完整']);
        exit;
    }
    
    $message = trim($input['message']);
    $burn_time = intval($input['burn_time']);
    $max_views = intval($input['max_views']);
    
    if (empty($message)) {
        echo json_encode(['success' => false, 'message' => '消息内容不能为空']);
        exit;
    }
    
    if ($burn_time <= 0 || $burn_time > 604800) { // 最大7天
        echo json_encode(['success' => false, 'message' => '焚化时间不合法']);
        exit;
    }
    
    $id = generateId();
    $expires_at = date('Y-m-d H:i:s', time() + $burn_time);
    
    try {
        $stmt = $pdo->prepare("INSERT INTO messages (id, message, expires_at, max_views) VALUES (?, ?, ?, ?)");
        $stmt->execute([$id, $message, $expires_at, $max_views]);
        
        echo json_encode(['success' => true, 'id' => $id]);
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => '创建失败: ' . $e->getMessage()]);
    }
    
} elseif ($action === 'view') {
    // 查看焚化信息
    if (!isset($input['id'])) {
        echo json_encode(['success' => false, 'message' => '参数不完整']);
        exit;
    }
    
    $id = $input['id'];
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM messages WHERE id = ?");
        $stmt->execute([$id]);
        $message = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$message) {
            echo json_encode(['success' => false, 'message' => '消息不存在或已焚化']);
            exit;
        }
        
        $now = time();
        $expires_at_timestamp = strtotime($message['expires_at']);
        
        // 检查是否过期
        if ($now >= $expires_at_timestamp) {
            // 删除过期消息
            $delete_stmt = $pdo->prepare("DELETE FROM messages WHERE id = ?");
            $delete_stmt->execute([$id]);
            echo json_encode(['success' => false, 'message' => '消息已过期']);
            exit;
        }
        
        // 检查查看次数
        if ($message['max_views'] > 0 && $message['current_views'] >= $message['max_views']) {
            // 删除达到最大查看次数的消息
            $delete_stmt = $pdo->prepare("DELETE FROM messages WHERE id = ?");
            $delete_stmt->execute([$id]);
            echo json_encode(['success' => false, 'message' => '查看次数已达上限']);
            exit;
        }
        
        // 更新查看次数
        $new_views = $message['current_views'] + 1;
        $update_stmt = $pdo->prepare("UPDATE messages SET current_views = ? WHERE id = ?");
        $update_stmt->execute([$new_views, $id]);
        
        // 检查是否达到最大查看次数，如果是则删除
        if ($message['max_views'] > 0 && $new_views >= $message['max_views']) {
            $delete_stmt = $pdo->prepare("DELETE FROM messages WHERE id = ?");
            $delete_stmt->execute([$id]);
        }
        
        echo json_encode([
            'success' => true,
            'message' => $message['message'],
            'current_views' => $new_views,
            'max_views' => $message['max_views'],
            'time_remaining' => $expires_at_timestamp - $now
        ]);
        
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => '查询失败: ' . $e->getMessage()]);
    }
    
} else {
    echo json_encode(['success' => false, 'message' => '未知操作']);
}
?>
