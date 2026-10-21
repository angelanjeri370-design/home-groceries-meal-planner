--Stores each shopping trip and its set budget
CREATE TABLE budget_sessions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  budget_amount DECIMAL(10,2) NOT NULL,
  session_date DATE NOT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id)
);

--Stores each item added during a shopping trip
CREATE TABLE budget_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  session_id INT NOT NULL,
  user_id INT NOT NULL,
  item_name VARCHAR(100) NOT NULL,
  price DECIMAL(10,2) NOT NULL,
  FOREIGN KEY (session_id) REFERENCES budget_sessions(id),
  FOREIGN KEY (user_id) REFERENCES users(id)
);