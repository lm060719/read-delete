# read-delete
美观的阅后即焚网站
添加MySQL数据库，并对已添加的数据库执行以下SQL语句：
CREATE TABLE messages (
    id VARCHAR(32) PRIMARY KEY,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    max_views INT DEFAULT 0,
    current_views INT DEFAULT 0,
    INDEX idx_expires (expires_at)
);
