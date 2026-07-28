CREATE DATABASE IF NOT EXISTS sidbm_platform CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
CREATE DATABASE IF NOT EXISTS sidbm_shard_local CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
CREATE DATABASE IF NOT EXISTS sidbm_platform_test CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
CREATE DATABASE IF NOT EXISTS sidbm_shard_test CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;

CREATE USER IF NOT EXISTS 'sidbm'@'%' IDENTIFIED BY 'sidbm';
GRANT ALL PRIVILEGES ON sidbm_platform.* TO 'sidbm'@'%';
GRANT ALL PRIVILEGES ON sidbm_shard_local.* TO 'sidbm'@'%';
GRANT ALL PRIVILEGES ON sidbm_platform_test.* TO 'sidbm'@'%';
GRANT ALL PRIVILEGES ON sidbm_shard_test.* TO 'sidbm'@'%';
FLUSH PRIVILEGES;
