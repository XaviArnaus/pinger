<?php

class FilesystemCleaner {
    const DEFAULT_TEMPLATE = "packed_%s.zip";
    private $output_path = "";
    private $output_path_zips = "";
    private $results_folders_pattern;
    private $unwanted_files = ['..', '.'];
    private $now;

    public function __construct(Config $config) {
        $this->now = date("Y-m-d");
        $this->output_path = sprintf($config->getParam("output_path", "results_%s"), $this->now);
        $this->output_path_zips = sprintf($config->getParam("output_path", "results_%s"), "old");
        $this->results_folders_pattern = sprintf($config->getParam("output_path", "results_%s"), "");
    }

    public function archiveOldResultFiles() {
        $this->createOutputPath();
        $files_by_dir = $this->getJsonFilesByDir();
        foreach($files_by_dir as $dir => $files) {
            $zip_filename = $this->output_path_zips . DIRECTORY_SEPARATOR . sprintf(
                    self::DEFAULT_TEMPLATE,
                    str_replace(
                        $this->results_folders_pattern,
                        "",
                        $dir
                    )
                );

            $this->zipFiles($files, $zip_filename);
            $this->deleteOldDirs($dir);
        }
    }

    private function zipFiles($files, $zip_filename) {
        $zip = new ZipArchive;
        if ($zip->open($zip_filename, ZipArchive::CREATE) === TRUE) {
            foreach($files as $json_file) {
                $zip->addFile($json_file);
            }
            $zip->close();
        }
    }

    private function deleteOldDirs($dir) {
        $files = array_diff(scandir($dir), array('.','..'));
        foreach ($files as $file) {
            (is_dir($dir . DIRECTORY_SEPARATOR . $file)) ?
                $this->deleteOldDirs($dir . DIRECTORY_SEPARATOR . $file) :
                unlink($dir . DIRECTORY_SEPARATOR . $file);
        }
        return rmdir($dir);
    }

    private function getZipFileNameFromJsonFile($filename) {
        return str_replace(".json", "", $filename);
    }

    private function getJsonFilesByDir() {
        $packages = [];
        $directories = $this->getResultDirectoriesToClean();
        foreach($directories as $directory) {
            $packages[$directory] = array_map(
                function($file) use ($directory) {
                    return $directory . DIRECTORY_SEPARATOR . $file;
                },
                array_filter(
                    array_diff(scandir($directory), $this->unwanted_files),
                    function ($filename) {
                        // Only the JSON files.
                        return strpos($filename, ".json") !== false;
                    }
                )
            );
        }

        return $packages;
    }

    private function getResultDirectoriesToClean() {
        return array_filter(
            array_diff(scandir('.'), $this->unwanted_files),
            function ($filename) {
                return is_dir($filename) &&
                    strpos($filename, $this->results_folders_pattern) !== FALSE &&
                    $filename !== $this->output_path &&
                    $filename !== $this->output_path_zips;
            }
        );
    }

    private function createOutputPath() {
        if (file_exists($this->output_path_zips)) return;

        if (!mkdir($this->output_path_zips, 0777, true)) {
            throw new RuntimeException("Could not create output dir. Check permissions!");
        }
    }

}