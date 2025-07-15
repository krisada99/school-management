<?php
// --- SCRIPT FOR DATABASE TABLE INSTALLATION ---
// This script should be run only once.
// After successful installation, it's recommended to DELETE this file from the server.

ini_set('display_errors', 1);
error_reporting(E_ALL);

// Include the database connection file
require_once 'api/db_connect.php'; // ตรวจสอบให้แน่ใจว่า path ถูกต้อง

echo "<h1>School Management System - Database Installation Script</h1>";
echo "Attempting to create tables in database: '{$database}'...<br><hr>";

try {
    // Set PDO to throw exceptions on error
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $sql_statements = [
        // Table: SchoolInfo
        "CREATE TABLE IF NOT EXISTS `SchoolInfo` (
          `schoolId` VARCHAR(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
          `schoolName` VARCHAR(255) NOT NULL,
          `address` TEXT,
          `phone` VARCHAR(50),
          `email` VARCHAR(100),
          `principalName` VARCHAR(255),
          `logoUrl` VARCHAR(255),
          PRIMARY KEY (`schoolId`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        // Table: Users
        "CREATE TABLE IF NOT EXISTS `Users` (
          `userId` VARCHAR(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
          `prefix` VARCHAR(50) NOT NULL,
          `name` VARCHAR(100) NOT NULL,
          `surname` VARCHAR(100) NOT NULL,
          `employeeId` VARCHAR(50) DEFAULT NULL,
          `position` VARCHAR(100) NOT NULL,
          `phone` VARCHAR(50) DEFAULT NULL,
          `email` VARCHAR(100) DEFAULT NULL,
          `username` VARCHAR(50) NOT NULL,
          `password` VARCHAR(255) NOT NULL,
          `role` VARCHAR(20) NOT NULL,
          `resetStatus` VARCHAR(20) DEFAULT NULL,
          `permissions` JSON DEFAULT NULL,
          PRIMARY KEY (`userId`),
          UNIQUE KEY `username` (`username`),
          UNIQUE KEY `employeeId` (`employeeId`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        // Table: Students
        "CREATE TABLE IF NOT EXISTS `Students` (
          `studentId` VARCHAR(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
          `studentCode` VARCHAR(50) NOT NULL,
          `prefix` VARCHAR(50) NOT NULL,
          `name` VARCHAR(100) NOT NULL,
          `surname` VARCHAR(100) NOT NULL,
          `dateOfBirth` DATE DEFAULT NULL,
          `gender` VARCHAR(20) DEFAULT NULL,
          `grade` VARCHAR(50) DEFAULT NULL,
          `class` VARCHAR(50) DEFAULT NULL,
          `parentName` VARCHAR(255) DEFAULT NULL,
          `parentPhone` VARCHAR(50) DEFAULT NULL,
          PRIMARY KEY (`studentId`),
          UNIQUE KEY `studentCode` (`studentCode`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        // New Table: ClassLevels
        "CREATE TABLE IF NOT EXISTS `ClassLevels` (
          `classLevelId` VARCHAR(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
          `className` VARCHAR(100) NOT NULL,
          `description` TEXT,
          PRIMARY KEY (`classLevelId`),
          UNIQUE KEY `className` (`className`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
        
        // --- START: ✨ โค้ดที่แก้ไข ---
        // MODIFIED TABLE: Subjects (Added subjectType)
        "CREATE TABLE IF NOT EXISTS `Subjects` (
          `subjectId` VARCHAR(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
          `subjectCode` VARCHAR(50) NOT NULL,
          `subjectName` VARCHAR(255) NOT NULL,
          `credits` DECIMAL(4,2) DEFAULT NULL,
          `subjectType` VARCHAR(50) NOT NULL DEFAULT 'วิชาพื้นฐาน' COMMENT 'ประเภทวิชา เช่น วิชาพื้นฐาน, วิชาเพิ่มเติม, วิชาเลือกเสรี, กิจกรรมพัฒนาผู้เรียน',
          PRIMARY KEY (`subjectId`),
          UNIQUE KEY `subjectCode` (`subjectCode`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
        // --- END: ✨ โค้ดที่แก้ไข ---

        // NEW TABLE: SupervisionRecords
        "CREATE TABLE IF NOT EXISTS `SupervisionRecords` (
            `recordId` VARCHAR(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
            `supervisionDate` DATE NOT NULL,
            `supervisorId` VARCHAR(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
            `superviseeId` VARCHAR(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
            `focusArea` TEXT,
            `observations` TEXT,
            `feedback` TEXT,
            `followUp` TEXT,
            `status` VARCHAR(50) DEFAULT 'Completed',
            `createdAt` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`recordId`),
            FOREIGN KEY (`supervisorId`) REFERENCES `Users`(`userId`) ON DELETE CASCADE,
            FOREIGN KEY (`superviseeId`) REFERENCES `Users`(`userId`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        // NEW TABLE: SupervisionResultFiles
        "CREATE TABLE IF NOT EXISTS `SupervisionResultFiles` (
            `fileId` VARCHAR(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
            `recordId` VARCHAR(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
            `fileName` VARCHAR(255) NOT NULL,
            `fileUrl` VARCHAR(255) NOT NULL,
            `fileType` VARCHAR(100) NOT NULL,
            `uploadedAt` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`fileId`),
            FOREIGN KEY (`recordId`) REFERENCES `SupervisionRecords`(`recordId`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        // NEW TABLE: SupervisionPhotos
        "CREATE TABLE IF NOT EXISTS `SupervisionPhotos` (
            `photoId` VARCHAR(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
            `recordId` VARCHAR(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
            `photoName` VARCHAR(255) NOT NULL,
            `photoUrl` VARCHAR(255) NOT NULL,
            `photoType` VARCHAR(100) NOT NULL,
            `orderNum` INT DEFAULT 0,
            `uploadedAt` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`photoId`),
            FOREIGN KEY (`recordId`) REFERENCES `SupervisionRecords`(`recordId`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        // TimetableEntries TABLE DEFINITION
        "CREATE TABLE IF NOT EXISTS `TimetableEntries` (
          `entryId` VARCHAR(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
          `classLevelId` VARCHAR(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
          `subjectId` VARCHAR(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
          `teacherId` VARCHAR(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
          `studentId` VARCHAR(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
          `dayOfWeek` VARCHAR(20) NOT NULL,
          `startTime` TIME NOT NULL,
          `endTime` TIME NOT NULL,
          `room` VARCHAR(50) DEFAULT NULL,
          `academicYear` VARCHAR(10) DEFAULT NULL,
          `semester` VARCHAR(10) DEFAULT NULL,
          `createdAt` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`entryId`),
          FOREIGN KEY (`classLevelId`) REFERENCES `ClassLevels`(`classLevelId`) ON DELETE CASCADE,
          FOREIGN KEY (`subjectId`) REFERENCES `Subjects`(`subjectId`) ON DELETE CASCADE,
          FOREIGN KEY (`teacherId`) REFERENCES `Users`(`userId`) ON DELETE CASCADE,
          FOREIGN KEY (`studentId`) REFERENCES `Students`(`studentId`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        // TimetableStudentLinks TABLE - MANY-TO-MANY RELATIONSHIP
        "CREATE TABLE IF NOT EXISTS `TimetableStudentLinks` (
            `linkId` VARCHAR(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
            `entryId` VARCHAR(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
            `studentId` VARCHAR(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
            PRIMARY KEY (`linkId`),
            UNIQUE KEY `entry_student_unique` (`entryId`, `studentId`),
            FOREIGN KEY (`entryId`) REFERENCES `TimetableEntries`(`entryId`) ON DELETE CASCADE,
            FOREIGN KEY (`studentId`) REFERENCES `Students`(`studentId`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        // AcademicTerms TABLE
        "CREATE TABLE IF NOT EXISTS `AcademicTerms` (
            `termId` VARCHAR(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
            `academicYear` VARCHAR(10) NOT NULL,
            `semester` VARCHAR(10) NOT NULL,
            `startDate` DATE NOT NULL,
            `endDate` DATE NOT NULL,
            `isCurrent` BOOLEAN DEFAULT FALSE,
            PRIMARY KEY (`termId`),
            UNIQUE KEY `year_semester_unique` (`academicYear`, `semester`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        // FiscalYears TABLE
        "CREATE TABLE IF NOT EXISTS `FiscalYears` (
            `fiscalYearId` VARCHAR(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
            `year` VARCHAR(10) NOT NULL,
            `startDate` DATE NOT NULL,
            `endDate` DATE NOT NULL,
            `isCurrent` BOOLEAN DEFAULT FALSE,
            PRIMARY KEY (`fiscalYearId`),
            UNIQUE KEY `year_unique` (`year`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        // Holidays TABLE
        "CREATE TABLE IF NOT EXISTS `Holidays` (
            `holidayId` VARCHAR(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
            `fiscalYearId` VARCHAR(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
            `description` VARCHAR(255) NOT NULL,
            `startDate` DATE NOT NULL,
            `endDate` DATE NOT NULL,
            PRIMARY KEY (`holidayId`),
            FOREIGN KEY (`fiscalYearId`) REFERENCES `FiscalYears`(`fiscalYearId`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        // --- START: ✨ โค้ดที่เพิ่มใหม่ ---
        // Add subjectType column to Subjects table if it doesn't exist
        "ALTER TABLE `Subjects` 
            ADD COLUMN IF NOT EXISTS `subjectType` VARCHAR(50) NOT NULL DEFAULT 'วิชาพื้นฐาน' 
            COMMENT 'ประเภทวิชา เช่น วิชาพื้นฐาน, วิชาเพิ่มเติม, วิชาเลือกเสรี, กิจกรรมพัฒนาผู้เรียน' 
            AFTER `credits`;",
        
        // --- DIGITAL DOCUMENTS TABLES ---
        "CREATE TABLE IF NOT EXISTS `DigitalDocuments` (
            `docId` VARCHAR(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
            `docNumber` VARCHAR(50) NOT NULL,
            `docTitle` VARCHAR(255) NOT NULL,
            `docDescription` TEXT,
            `academicYear` VARCHAR(10) NOT NULL,
            `semester` VARCHAR(10) NOT NULL,
            `department` VARCHAR(100) NOT NULL,
            `uploadedBy` VARCHAR(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
            `uploadedAt` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`docId`),
            UNIQUE KEY `docNumber` (`docNumber`),
            FOREIGN KEY (`uploadedBy`) REFERENCES `Users`(`userId`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        "CREATE TABLE IF NOT EXISTS `DocumentFiles` (
            `fileId` VARCHAR(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
            `docId` VARCHAR(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
            `fileName` VARCHAR(255) NOT NULL,
            `fileUrl` VARCHAR(255) NOT NULL,
            `fileType` VARCHAR(100) NOT NULL,
            `fileSize` INT NOT NULL,
            PRIMARY KEY (`fileId`),
            FOREIGN KEY (`docId`) REFERENCES `DigitalDocuments`(`docId`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
        // --- END: ✨ โค้ดที่เพิ่มใหม่ ---
    ];

    foreach ($sql_statements as $sql) {
        $tableName = "Unknown";
        if (preg_match('/CREATE TABLE IF NOT EXISTS `(\w+)`/i', $sql, $matches) || preg_match('/ALTER TABLE `(\w+)`/i', $sql, $matches)) {
            $tableName = $matches[1];
        }
        $conn->exec($sql);
        echo "<p style='color:green;'>Table '{$tableName}' created/modified successfully or already exists.</p>";
    }

    echo "<hr><h2 style='color:blue;'>Installation Complete!</h2>";
    echo "<p style='color:red; font-weight:bold;'>IMPORTANT: Please delete this file (install_db.php) from your server now for security reasons.</p>";

} catch(PDOException $e) {
    echo "<h2 style='color:red;'>An error occurred:</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
}

// Close the connection
$conn = null;
?>