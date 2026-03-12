-- Run this if you have an existing ServiceLink database (adds face verification columns)
-- Execute in phpMyAdmin or MySQL. Skip if column already exists.
USE servicelink;

ALTER TABLE providers ADD COLUMN face_verified TINYINT(1) DEFAULT 0;
ALTER TABLE providers ADD COLUMN face_verification_rejected TINYINT(1) DEFAULT 0;
