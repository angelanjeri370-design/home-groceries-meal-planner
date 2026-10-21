CREATE TABLE meal_plans (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  plan_date DATE NOT NULL,
  meal_type VARCHAR(20) NOT NULL,
  meal_name VARCHAR(100) NOT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id)
);