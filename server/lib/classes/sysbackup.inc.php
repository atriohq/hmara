<?php
/*
Copyright (c) 2025, Till Brehm - ISPConfig UG
All rights reserved.

Redistribution and use in source and binary forms, with or without modification,
are permitted provided that the following conditions are met:

    * Redistributions of source code must retain the above copyright notice,
      this list of conditions and the following disclaimer.
    * Redistributions in binary form must reproduce the above copyright notice,
      this list of conditions and the following disclaimer in the documentation
      and/or other materials provided with the distribution.
    * Neither the name of ISPConfig nor the names of its contributors
      may be used to endorse or promote products derived from this software without
      specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED.
IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT,
INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY
OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING
NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE,
EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
*/

/**
 * Class sysbackup
 *
 * This class provides methods to backup and restore a MySQL database and a directory.
 * Backups are stored as compressed files:
 * - Databases are backed up with mysqldump and compressed to .sql.gz files.
 * - Directories are archived with tar and compressed to .tar.gz files.
 *
 * The class checks for sufficient free disk space (set as a class variable in MB) before starting a backup,
 * and maintains a maximum number of backups by deleting the oldest files when necessary.
 */
class sysbackup {
    /**
     * @var int Minimum free space (in bytes) required in the backup folder.
     */
    private $minFreeSpace;

    /**
     * Constructor.
     *
     * @param int $minFreeSpaceMB Minimum free space in megabytes required to perform a backup (default: 100 MB).
     */
    public function __construct($minFreeSpaceMB = 100) {
        // Convert MB to bytes.
        $this->minFreeSpace = $minFreeSpaceMB * 1024 * 1024;
    }

    /**
     * Backup a MySQL database using mysqldump and compress the output with gzip.
     *
     * @param string $backupFolder The folder where backups will be stored.
     * @param string $host MySQL server hostname.
     * @param int    $port MySQL server port.
     * @param string $dbName The name of the database to backup.
     * @param string $username MySQL username.
     * @param string $password MySQL password.
     * @param int    $maxBackups The number of backup copies to keep.
     * @return string Returns the backup file path on success.
     * @throws Exception if any step of the backup process fails.
     */
    public function backupDatabase($backupFolder, $host, $port, $dbName, $username, $password, $maxBackups) {
        // Ensure the backup folder exists.
        if (!is_dir($backupFolder)) {
            if (!mkdir($backupFolder, 0700, true)) {
                throw new Exception("Failed to create backup directory: $backupFolder");
            }
        }

        // Check for minimum free space.
        $freeSpace = disk_free_space($backupFolder);
        if ($freeSpace < $this->minFreeSpace) {
            throw new Exception("Not enough free disk space. Required: " . ($this->minFreeSpace / 1024 / 1024) .
                " MB, available: " . ($freeSpace / 1024 / 1024) . " MB.");
        }

        // Generate backup file name using database name and current date/time.
        $date = date('Y-m-d_H-i-s');
        $backupFile = rtrim($backupFolder, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR .
                      $dbName . "_" . $date . ".sql.gz";

        // Escape shell arguments.
        $escapedHost       = escapeshellarg($host);
        $escapedPort       = escapeshellarg($port);
        $escapedUsername   = escapeshellarg($username);
        $escapedPassword   = escapeshellarg($password);
        $escapedDbName     = escapeshellarg($dbName);
        $escapedBackupFile = escapeshellarg($backupFile);

        // Build and execute the backup command.
        // Note: There is no space between -p and the password.
        $command = "mysqldump --single-transaction -h $escapedHost -P $escapedPort -u $escapedUsername -p$escapedPassword $escapedDbName | gzip > $escapedBackupFile";
        exec($command, $output, $returnVar);
        if ($returnVar !== 0) {
            throw new Exception("Database backup failed with error code: $returnVar");
        }

        // Set file permissions to 0600.
        if (!chmod($backupFile, 0600)) {
            throw new Exception("Failed to set permissions on backup file: $backupFile");
        }

        // Remove old backup files if exceeding $maxBackups.
        $pattern = rtrim($backupFolder, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $dbName . "_*.sql.gz";
        $files = glob($pattern);
        if ($files !== false && count($files) > $maxBackups) {
            // Sort by modification time (oldest first).
            usort($files, function($a, $b) {
                return filemtime($a) - filemtime($b);
            });
            $filesToDelete = count($files) - $maxBackups;
            for ($i = 0; $i < $filesToDelete; $i++) {
                if (!unlink($files[$i])) {
                    throw new Exception("Failed to delete old backup file: " . $files[$i]);
                }
            }
        }

        return $backupFile;
    }

    /**
     * Restore a MySQL database from a gzipped SQL backup file.
     *
     * @param string $backupFile The path to the backup file (e.g., "/path/to/backup/folder/dbname_date.sql.gz").
     * @param string $host MySQL server hostname.
     * @param int    $port MySQL server port.
     * @param string $dbName The name of the database to restore.
     * @param string $username MySQL username.
     * @param string $password MySQL password.
     * @return bool Returns true on success.
     * @throws Exception if the restore process fails.
     */
    public function restoreDatabase($backupFile, $host, $port, $dbName, $username, $password) {
        // Validate backup file.
        if (!file_exists($backupFile)) {
            throw new Exception("Backup file does not exist: $backupFile");
        }
        if (!is_readable($backupFile)) {
            throw new Exception("Backup file is not readable: $backupFile");
        }

        // Escape shell arguments.
        $escapedBackupFile = escapeshellarg($backupFile);
        $escapedHost       = escapeshellarg($host);
        $escapedPort       = escapeshellarg($port);
        $escapedUsername   = escapeshellarg($username);
        $escapedPassword   = escapeshellarg($password);
        $escapedDbName     = escapeshellarg($dbName);

        // Build and execute the restore command.
        $command = "gunzip -c $escapedBackupFile | mysql -h $escapedHost -P $escapedPort -u $escapedUsername -p$escapedPassword $escapedDbName";
        exec($command, $output, $returnVar);
        if ($returnVar !== 0) {
            throw new Exception("Database restore failed with error code: $returnVar");
        }

        return true;
    }

    /**
     * Backup a directory by creating a tar.gz archive.
     *
     * @param string $backupFolder The folder where backups will be stored.
     * @param string $sourceDirectory The directory to backup.
     * @param int    $maxBackups The number of backup copies to keep.
     * @return string Returns the backup file path on success.
     * @throws Exception if any step of the backup process fails.
     */
    public function backupDirectory($backupFolder, $sourceDirectory, $maxBackups) {
        // Validate source directory.
        if (!is_dir($sourceDirectory)) {
            throw new Exception("Source directory does not exist: $sourceDirectory");
        }

        // Ensure the backup folder exists.
        if (!is_dir($backupFolder)) {
            if (!mkdir($backupFolder, 0700, true)) {
                throw new Exception("Failed to create backup directory: $backupFolder");
            }
        }

        // Check for minimum free space.
        $freeSpace = disk_free_space($backupFolder);
        if ($freeSpace < $this->minFreeSpace) {
            throw new Exception("Not enough free disk space. Required: " . ($this->minFreeSpace / 1024 / 1024) .
                " MB, available: " . ($freeSpace / 1024 / 1024) . " MB.");
        }

        // Use the base name of the source directory for the backup filename.
        $dirName = basename($sourceDirectory);
        $date = date('Y-m-d_H-i-s');
        $backupFile = rtrim($backupFolder, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR .
                      $dirName . "_" . $date . ".tar.gz";

        // Escape shell arguments.
        $escapedBackupFile = escapeshellarg($backupFile);
        // To avoid storing the full absolute path in the archive, use -C.
        $parentDir = dirname($sourceDirectory);
        $escapedParentDir = escapeshellarg($parentDir);
        $escapedDirName = escapeshellarg($dirName);

        // Build and execute the backup command.
        $command = "tar -czf $escapedBackupFile -C $escapedParentDir $escapedDirName";
        exec($command, $output, $returnVar);
        if ($returnVar !== 0) {
            throw new Exception("Directory backup failed with error code: $returnVar");
        }

        // Set file permissions to 0600.
        if (!chmod($backupFile, 0600)) {
            throw new Exception("Failed to set permissions on directory backup file: $backupFile");
        }

        // Remove old backup files if exceeding $maxBackups.
        $pattern = rtrim($backupFolder, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $dirName . "_*.tar.gz";
        $files = glob($pattern);
        if ($files !== false && count($files) > $maxBackups) {
            // Sort by modification time (oldest first).
            usort($files, function($a, $b) {
                return filemtime($a) - filemtime($b);
            });
            $filesToDelete = count($files) - $maxBackups;
            for ($i = 0; $i < $filesToDelete; $i++) {
                if (!unlink($files[$i])) {
                    throw new Exception("Failed to delete old directory backup file: " . $files[$i]);
                }
            }
        }

        return $backupFile;
    }

    /**
     * Restore a directory from a tar.gz backup file.
     *
     * @param string $backupFile The path to the tar.gz backup file.
     * @param string $destinationDirectory The directory where the backup should be restored.
     * @return bool Returns true on success.
     * @throws Exception if the restore process fails.
     */
    public function restoreDirectory($backupFile, $destinationDirectory) {
        // Validate backup file.
        if (!file_exists($backupFile)) {
            throw new Exception("Backup file does not exist: $backupFile");
        }
        if (!is_readable($backupFile)) {
            throw new Exception("Backup file is not readable: $backupFile");
        }

        // Ensure the destination directory exists.
        if (!is_dir($destinationDirectory)) {
            if (!mkdir($destinationDirectory, 0755, true)) {
                throw new Exception("Failed to create destination directory: $destinationDirectory");
            }
        }

        // Escape shell arguments.
        $escapedBackupFile = escapeshellarg($backupFile);
        $escapedDestination = escapeshellarg($destinationDirectory);

        // Build and execute the restore command.
        $command = "tar -xzf $escapedBackupFile -C $escapedDestination";
        exec($command, $output, $returnVar);
        if ($returnVar !== 0) {
            throw new Exception("Directory restore failed with error code: $returnVar");
        }

        return true;
    }

    /**
     * Get statistics about backup files found in the given directory.
     *
     * The function scans the directory for backup files with the naming convention:
     * - MySQL backups: [dbName]_YYYY-MM-DD_HH-II-SS.sql.gz
     * - Directory backups: [dirName]_YYYY-MM-DD_HH-II-SS.tar.gz
     *
     * For each file, it returns:
     * - file_name: The backup file name.
     * - backup_type: "mysql" for .sql.gz files, "file" for .tar.gz files.
     * - backup_date: The date extracted from the file name.
     * - size_mb: The file size in megabytes.
     *
     * @param string $backupFolder The directory to scan for backup files.
     * @return array A multidimensional array of backup statistics.
     * @throws Exception if the backup folder does not exist.
     */
    public function backupStats($backupFolder) {
        if (!is_dir($backupFolder)) {
            throw new Exception("Backup folder does not exist: $backupFolder");
        }

        // Get all .gz files in the folder.
        $files = glob(rtrim($backupFolder, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*.gz');
        $stats = [];

        foreach ($files as $file) {
            $filename = basename($file);
            $sizeMB = round(filesize($file) / (1024 * 1024), 2);

            // Determine backup type and pattern for extracting the date.
            if (substr($filename, -7) === '.sql.gz') {
                $type = 'mysql';
                $pattern = '/^(.*)_([\d]{4}-[\d]{2}-[\d]{2}_[\d]{2}-[\d]{2}-[\d]{2})\.sql\.gz$/';
            } elseif (substr($filename, -7) === '.tar.gz') {
                $type = 'file';
                $pattern = '/^(.*)_([\d]{4}-[\d]{2}-[\d]{2}_[\d]{2}-[\d]{2}-[\d]{2})\.tar\.gz$/';
            } else {
                // Skip files that don't match the expected backup patterns.
                continue;
            }

            $backupDate = 'Unknown';
            if (preg_match($pattern, $filename, $matches)) {
                $backupDate = $matches[2];
            }

            $stats[] = [
                'file_name'   => $filename,
                'backup_type' => $type,
                'backup_date' => $backupDate,
                'size_mb'     => $sizeMB,
            ];
        }

        return $stats;
    }
}
