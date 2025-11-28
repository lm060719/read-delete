<?php
// cleanup.php - 定期清理过期数据脚本
header('Content-Type: text/plain; charset=utf-8');

// 数据库配置 - 请使用与api.php相同的数据库配置
$host = 'localhost';
$dbname = 'burn_user';  // 数据库名
$username = 'burn_user';  // 用户名
$password = 'limo060719';  // 密码

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password, array(
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"
    ));
    
    // 删除所有过期的消息
    $stmt = $pdo->prepare("DELETE FROM messages WHERE expires_at < NOW()");
    $stmt->execute();
    
    $deleted_count = $stmt->rowCount();
    
    echo "清理完成！\n";
    echo "删除了 $deleted_count 条过期消息\n";
    echo "执行时间：" . date('Y-m-d H:i:s') . "\n";
    
} catch(PDOException $e) {
    echo "清理失败: " . $e->getMessage() . "\n";
    error_log("清理脚本执行失败: " . $e->getMessage());
}
?>