ALTER TABLE users MODIFY role ENUM('admin','editor','writer','author','user') NOT NULL DEFAULT 'user'; UPDATE users SET role='writer' WHERE role='user' AND email IN ('writer@example.com');
