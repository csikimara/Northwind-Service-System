CREATE TABLE customers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  phone VARCHAR(80),
  email VARCHAR(255),
  address TEXT,
  customer_type VARCHAR(80),
  note TEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE jobs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  customer_id INT NOT NULL,
  job_type ENUM('karbantartas','javitas','telepites','felmeres','egyeb') DEFAULT 'karbantartas',
  status ENUM('varolista','idopontozva','folyamatban','kesz','szamlazva','torolve') DEFAULT 'varolista',
  scheduled_date DATE,
  scheduled_time TIME,
  title VARCHAR(255),
  description TEXT,
  calendar_note TEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (customer_id) REFERENCES customers(id)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE job_zones (
  id INT AUTO_INCREMENT PRIMARY KEY,
  job_id INT NOT NULL,
  zone_name VARCHAR(255) NOT NULL,
  sort_order INT DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE job_rooms (
  id INT AUTO_INCREMENT PRIMARY KEY,
  job_id INT NOT NULL,
  zone_id INT NOT NULL,
  room_name VARCHAR(255) NOT NULL,
  maintenance_mode ENUM(
    'nincs',
    'alap',
    'nagy_zsakos',
    'javitas',
    'felmeres',
    'alkatresz_szukseges',
    'nem_hozzaferheto',
    'ugyfel_nem_kerte'
  ) DEFAULT 'nincs',
  indoor_unit_type VARCHAR(255),
  indoor_serial VARCHAR(255),
  outdoor_unit_type VARCHAR(255),
  outdoor_serial VARCHAR(255),
  note TEXT,
  sort_order INT DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE,
  FOREIGN KEY (zone_id) REFERENCES job_zones(id) ON DELETE CASCADE
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE job_photos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  job_id INT NOT NULL,
  room_id INT NOT NULL,
  photo_type ENUM(
    'belteri_matrica',
    'kulteri_matrica',
    'allapot_elotte',
    'allapot_utana',
    'hiba',
    'alkatresz',
    'egyeb'
  ) DEFAULT 'egyeb',
  original_filename VARCHAR(255),
  stored_filename VARCHAR(255) NOT NULL,
  nas_path TEXT NOT NULL,
  public_preview_path TEXT,
  note TEXT,
  uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE,
  FOREIGN KEY (room_id) REFERENCES job_rooms(id) ON DELETE CASCADE
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE events (
  id INT AUTO_INCREMENT PRIMARY KEY,
  job_id INT NULL,
  customer_id INT NULL,
  title VARCHAR(255) NOT NULL,
  event_date DATE NOT NULL,
  start_time TIME,
  end_time TIME,
  location TEXT,
  note TEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE SET NULL,
  FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE room_templates (
  id INT AUTO_INCREMENT PRIMARY KEY,
  template_name VARCHAR(255) NOT NULL,
  zone_name VARCHAR(255) NOT NULL,
  room_name VARCHAR(255) NOT NULL,
  sort_order INT DEFAULT 0
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

INSERT INTO room_templates (template_name, zone_name, room_name, sort_order) VALUES
('csaladi_haz', 'Földszint', 'Nappali', 10),
('csaladi_haz', 'Földszint', 'Szülői háló 1', 20),
('csaladi_haz', 'Földszint', 'Szülői háló 2', 30),
('csaladi_haz', 'Földszint', 'Gyerekszoba 1', 40),
('csaladi_haz', 'Földszint', 'Gyerekszoba 2', 50),
('csaladi_haz', 'Földszint', 'Gyerekszoba 3', 60),
('csaladi_haz', 'Földszint', 'Gyerekszoba 4', 70),
('csaladi_haz', 'Földszint', 'Dolgozó', 80),
('csaladi_haz', 'Földszint', 'Gardrób', 90),
('csaladi_haz', 'Földszint', 'Előtér', 100),
('csaladi_haz', 'Földszint', 'Dühöngő', 110),
('csaladi_haz', 'Földszint', 'Konyha', 120),
('csaladi_haz', 'Földszint', 'Étkező', 130),
('csaladi_haz', 'Földszint', 'Egyéb', 140),
('csaladi_haz', 'Emelet 1', 'Nappali', 10),
('csaladi_haz', 'Emelet 1', 'Szülői háló 1', 20),
('csaladi_haz', 'Emelet 1', 'Szülői háló 2', 30),
('csaladi_haz', 'Emelet 1', 'Gyerekszoba 1', 40),
('csaladi_haz', 'Emelet 1', 'Gyerekszoba 2', 50),
('csaladi_haz', 'Emelet 1', 'Gyerekszoba 3', 60),
('csaladi_haz', 'Emelet 1', 'Gyerekszoba 4', 70),
('csaladi_haz', 'Emelet 1', 'Dolgozó', 80),
('csaladi_haz', 'Emelet 1', 'Gardrób', 90),
('csaladi_haz', 'Emelet 1', 'Előtér', 100),
('csaladi_haz', 'Emelet 1', 'Dühöngő', 110),
('csaladi_haz', 'Emelet 1', 'Konyha', 120),
('csaladi_haz', 'Emelet 1', 'Étkező', 130),
('csaladi_haz', 'Emelet 1', 'Egyéb', 140),
('irodahaz', 'Irodaház', 'Igazgatói iroda', 10),
('irodahaz', 'Irodaház', 'Könyvelés', 20),
('irodahaz', 'Irodaház', 'Pénzügy', 30),
('irodahaz', 'Irodaház', 'Konyha', 40),
('irodahaz', 'Irodaház', 'Üzemeltetés', 50),
('irodahaz', 'Irodaház', 'Tárgyaló 1', 60),
('irodahaz', 'Irodaház', 'Tárgyaló 2', 70),
('irodahaz', 'Irodaház', 'Recepció', 80),
('irodahaz', 'Irodaház', 'Szerverhelyiség', 90),
('irodahaz', 'Irodaház', 'Open office', 100),
('irodahaz', 'Irodaház', 'Raktár', 110),
('irodahaz', 'Irodaház', 'Egyéb', 120);