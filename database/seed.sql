-- Optional development-only sample data for Campus Lost-and-Found Portal.
-- Run after schema.sql. Do not use these demo credentials in production.
-- Demo password for both accounts: password
-- The password_hash below is a bcrypt hash for the demo password.
-- Replace/remove these accounts before deploying anywhere public.

BEGIN;

INSERT INTO users (full_name, email, password_hash, role)
VALUES
    ('Demo Student', 'student@example.test',
     '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user'),
    ('Demo Admin', 'admin@example.test',
     '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin')
ON CONFLICT (email) DO NOTHING;

INSERT INTO reports
    (user_id, item_name, report_type, category, location, item_date, description, status)
SELECT u.id, 'Blue water bottle', 'found', 'Bottle', 'Main Library, Ground Floor',
       CURRENT_DATE, 'A blue reusable bottle was found near the reading area.', 'open'
FROM users u
WHERE u.email = 'student@example.test'
  AND NOT EXISTS (
      SELECT 1 FROM reports r
      WHERE r.user_id = u.id AND r.item_name = 'Blue water bottle'
        AND r.report_type = 'found'
  );

INSERT INTO reports
    (user_id, item_name, report_type, category, location, item_date, description, status)
SELECT u.id, 'Student ID card', 'lost', 'ID Card', 'Canteen',
       CURRENT_DATE, 'A student ID card may have been misplaced near the canteen.', 'open'
FROM users u
WHERE u.email = 'student@example.test'
  AND NOT EXISTS (
      SELECT 1 FROM reports r
      WHERE r.user_id = u.id AND r.item_name = 'Student ID card'
        AND r.report_type = 'lost'
  );

COMMIT;
