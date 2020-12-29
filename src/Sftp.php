<?php
namespace tenglin\basepack;

use phpseclib\Net\SFTP as SecFtp;
use Exception;

/**
 *
 * project Url: https://github.com/hugsbrugs/php-sftp
 *
 * use: $sftp = \Tenglin\Extpack\Sftp::connect(array[server, user, password, [port]]);
 * use: $sftp->test();
 *
 * use: \tenglin\basepack\Sftp::connect(array[server, user, password, [port]])->test();
 *
 * or use: \tenglin\basepack\Sftp::connect(string server, string user, string password, [string port])->test();
 *
 */

class Sftp {
	protected static $sftp = false;
	protected static $DS = "linux"; // Sftp Server system version, Default linux.

	// new Sftp Login to SFTP server
	public function __construct($server, $user, $password, $port) {
		if (!class_exists("\phpseclib\Net\SFTP")) {
			echo "Please use Composer to install phpseclib, Composer: composer require phpseclib/phpseclib:~2.0";
			exit();
		}
		if (is_array($server)) {
			extract($server);
		}
		try {
			// Login to SFTP server
			self::$sftp = new SecFtp($server, $port);
			if (!self::$sftp->login($user, $password)) {
				self::$sftp = false;
			}
		} catch (Exception $e) {
			error_log("sftp login: " . $e->getMessage());
		}
		return self::$sftp;
	}

	public static function connect($server, $user = "", $password = "", $port = "") {
		if (is_array($server)) {
			extract($server);
		}
		if (empty($port)) {
			$port = 22;
		}
		return new static($server, $user, $password, $port);
	}

	// Test SFTP connection
	public static function test() {
		$result = false;
		if (self::$sftp !== false) {
			$result = true;
		}
		return $result;
	}

	// Get default login SFTP directory aka pwd
	public static function pwd() {
		$result = false;
		if (self::$sftp !== false) {
			$result = self::$sftp->pwd();
		}
		return $result;
	}

	// Check if a directory exists on SFTP Server
	public static function is_dir($directory) {
		$result = false;
		try {
			if (self::$sftp !== false) {
				if (self::$sftp->is_dir($directory)) {
					$result = true;
				}
			}
		} catch (Exception $e) {
			error_log("sftp is dir: " . $e->getMessage());
		}
		return $result;
	}

	// Create a directory on remote SFTP server
	public static function mkdir($directory, $chmod = true) {
		$result = false;
		try {
			if (self::$sftp !== false) {
				if (!self::$sftp->is_dir($directory)) {
					if (self::$sftp->mkdir($directory, $chmod)) {
						$result = true;
					}
				} else {
					$result = true;
				}
			}
		} catch (Exception $e) {
			error_log("sftp mkdir: " . $e->getMessage());
		}
		return $result;
	}

	// Recursively deletes files and folder in given directory
	public static function rmdir($remote_path) {
		$result = false;
		try {
			if (self::$sftp !== false) {
				if (self::clean_dir($remote_path, self::$sftp)) {
					if (!self::ends_with($remote_path, "/")) {
						if (self::$sftp->rmdir($remote_path)) {
							$result = true;
						}
					} else {
						$result = true;
					}
				}
			}
		} catch (Exception $e) {
			error_log("sftp rmdir: " . $e->getMessage());
		}
		return $result;
	}

	// List files in given directory on SFTP server
	public static function scandir($path) {
		$result = false;
		if (self::$sftp !== false) {
			$result = self::$sftp->nlist($path);
		}
		if (is_array($result)) {
			$result = array_diff($result, [".", ".."]);
		}
		return $result;
	}

	// Recursively copy files and folders on remote SFTP server
	public static function upload_dir($local_path, $remote_path) {
		$result = false;
		try {
			$remote_path = rtrim($remote_path, self::$DS);
			if (self::$sftp !== false) {
				if (!self::ends_with($local_path, "/")) {
					$remote_path = $remote_path . self::$DS . basename($local_path);
					self::$sftp->mkdir($remote_path, 0755);
				}
				if (self::$sftp->is_dir($remote_path)) {
					$result = self::upload_all(self::$sftp, $local_path, $remote_path);
				}
			}
		} catch (Exception $e) {
			error_log("sftp upload dir: " . $e->getMessage());
		}
		return $result;
	}

	// Download a directory from remote SFTP server
	public static function download_dir($remote_dir, $local_dir) {
		$result = false;
		try {
			if (!is_dir($local_dir) && !is_writable($local_dir)) {
				mkdir($local_dir, 0755);
			}
			if (self::$sftp !== false) {
				$result = self::download_all(self::$sftp, $remote_dir, $local_dir);
			}
		} catch (Exception $e) {
			error_log("sftp download dir: " . $e->getMessage());
		}
		return $result;
	}

	// Check if a file exists on SFTP Server
	public static function is_file($remote_file) {
		$result = false;
		try {
			if (self::$sftp !== false) {
				if (self::$sftp->is_file($remote_file)) {
					$result = true;
				}
			}
		} catch (Exception $e) {
			error_log("sftp is file: " . $e->getMessage());
		}
		return $result;
	}

	// Create and fill in a file on remote SFTP server
	public static function touch($remote_file, $content = "") {
		$result = false;
		try {
			if (self::$sftp !== false) {
				$local_file = tmpfile();
				fwrite($local_file, $content);
				fseek($local_file, 0);
				if (self::$sftp->put($remote_file, $local_file, SecFtp::SOURCE_LOCAL_FILE)) {
					$result = true;
				}
				fclose($local_file);
			}
		} catch (Exception $e) {
			error_log("sftp touch: " . $e->getMessage());
		}
		return $result;
	}

	// Upload a file on SFTP server
	public static function upload($local_file, $remote_file) {
		$result = false;
		try {
			if (self::$sftp !== false) {
				if (self::$sftp->put($remote_file, $local_file, SecFtp::SOURCE_LOCAL_FILE)) {
					$result = true;
				}
			}
		} catch (Exception $e) {
			error_log("sftp upload: " . $e->getMessage());
		}
		return $result;
	}

	// Rename a file on remote SFTP server
	public static function rename($current_filename, $new_filename) {
		$result = false;
		try {
			if (self::$sftp !== false) {
				if (self::$sftp->rename($current_filename, $new_filename)) {
					$result = true;
				}
			}
		} catch (Exception $e) {
			error_log("sftp rename: " . $e->getMessage());
		}
		return $result;
	}

	// Delete a file on remote SFTP server
	public static function delete($remote_file) {
		$result = false;
		try {
			if (self::$sftp !== false) {
				if (self::$sftp->is_file($remote_file)) {
					if (self::$sftp->delete($remote_file)) {
						$result = true;
					}
				}
			}
		} catch (Exception $e) {
			error_log("sftp delete: " . $e->getMessage());
		}
		return $result;
	}

	// Download a file from remote SFTP server
	public static function download($remote_file, $local_file) {
		$result = false;
		try {
			if (self::$sftp !== false) {
				if (self::$sftp->get($remote_file, $local_file)) {
					$result = true;
				}
			}
		} catch (Exception $e) {
			error_log("sftp download: " . $e->getMessage());
		}
		return $result;
	}

	// Recursively deletes files and folder
	protected static function clean_dir($remote_path, $sftp) {
		$result = false;
		$to_delete = 0;
		$deleted = 0;
		$list = $sftp->nlist($remote_path);
		foreach ($list as $element) {
			if ($element !== "." && $element !== "..") {
				$to_delete++;
				if ($sftp->is_dir($remote_path . self::$DS . $element)) {
					self::clean_dir($remote_path . self::$DS . $element, $sftp);
					if ($sftp->rmdir($remote_path . self::$DS . $element)) {
						$deleted++;
					}
				} else {
					if ($sftp->delete($remote_path . self::$DS . $element)) {
						$deleted++;
					}
				}
			}
		}
		if ($deleted === $to_delete) {
			$result = true;
		}
		return $result;
	}

	// Recursively copy files and folders on remote SFTP server
	protected static function upload_all($sftp, $local_dir, $remote_dir) {
		$result = false;
		try {
			if (!$sftp->is_dir($remote_dir)) {
				if (!$sftp->mkdir($remote_dir, 0755)) {
					throw new Exception("Cannot create remote directory.", 1);
				}
			}
			$to_upload = 0;
			$uploaded = 0;
			$d = dir($local_dir);
			while ($file = $d->read()) {
				if ($file != "." && $file != "..") {
					$to_upload++;
					if (is_dir($local_dir . self::$DS . $file)) {
						if (self::upload_all($sftp, $local_dir . self::$DS . $file, $remote_dir . self::$DS . $file)) {
							$uploaded++;
						}
					} else {
						if ($sftp->put($remote_dir . self::$DS . $file, $local_dir . self::$DS . $file, SecFtp::SOURCE_LOCAL_FILE)) {
							$uploaded++;
						}
					}
				}
			}
			$d->close();
			if ($to_upload === $uploaded) {
				$result = true;
			}
		} catch (Exception $e) {
			error_log("sftp upload all: " . $e->getMessage());
		}
		return $result;
	}

	// Recursive function to download remote files
	protected static function download_all($sftp, $remote_dir, $local_dir) {
		$result = false;
		try {
			if ($sftp->is_dir($remote_dir)) {
				$files = $sftp->nlist($remote_dir);
				if ($files !== false) {
					$to_download = 0;
					$downloaded = 0;
					foreach ($files as $file) {
						if ($file != "." && $file != "..") {
							$to_download++;
							if ($sftp->is_dir($remote_dir . self::$DS . $file)) {
								mkdir($local_dir . self::$DS . basename($file), 0755);
								if (self::download_all($sftp, $remote_dir . self::$DS . $file, $local_dir . self::$DS . basename($file))) {
									$downloaded++;
								}
							} else {
								if ($sftp->get($remote_dir . self::$DS . $file, $local_dir . self::$DS . basename($file))) {
									$downloaded++;
								}
							}
						}
					}
					if ($to_download === $downloaded) {
						$result = true;
					}
				} else {
					$result = true;
				}
			}
		} catch (Exception $e) {
			error_log("sftp download all: " . $e->getMessage());
		}
		return $result;
	}

	// Checks whether a string ends with given chars
	protected static function ends_with($haystack, $needle) {
		$length = strlen($needle);
		if ($length == 0) {
			return true;
		}
		return (substr($haystack, -$length) === $needle);
	}

	public static function linux() {
		self::$DS = "/";
	}

	public static function windows() {
		self::$DS = DIRECTORY_SEPARATOR;
	}
}
