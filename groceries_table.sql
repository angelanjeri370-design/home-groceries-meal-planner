CREATE TABLE groceries (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  item_name VARCHAR(100) NOT NULL,
  quantity DECIMAL(10,2) NOT NULL,
  unit VARCHAR(20) DEFAULT 'units',
  daily_consumption DECIMAL(10,2) DEFAULT NULL,
  last_updated DATE DEFAULT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id)
);