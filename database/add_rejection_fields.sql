ALTER TABLE `bookings` MODIFY COLUMN `status` ENUM('pending', 'confirmed', 'completed', 'cancelled', 'rejected') DEFAULT 'pending';
ALTER TABLE `bookings` ADD COLUMN `rejection_reason` TEXT NULL AFTER `notes`;
ALTER TABLE `bookings` ADD COLUMN `suggested_reschedule_date` DATETIME NULL AFTER `rejection_reason`;
