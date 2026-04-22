CREATE DATABASE IF NOT EXISTS budget_book
  CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
CREATE DATABASE IF NOT EXISTS budget_book_test
  CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;

GRANT ALL PRIVILEGES ON `budget_book`.* TO 'budget'@'%';
GRANT ALL PRIVILEGES ON `budget_book_test`.* TO 'budget'@'%';
FLUSH PRIVILEGES;
