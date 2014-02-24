<?php
    namespace Phork\Core;

    /**
     * The uploads class handles files uploaded via POST and has some 
     * additional file system checks before permanently saving the file.
     *
     * @author Elenor Collings <elenor@phork.org>
     * @package phork
     * @subpackage core
     */
    class Uploads
    {
        /**
         * Gets an array of the successfully uploaded files.
         *
         * @access public
         * @param boolean $uploaded Whether the file has to have been uploaded
         * @return array The uploaded files array
         * @static
         */
        static public function getFiles($uploaded = true)
        {
            $files = array();

            if (!empty($_FILES)) {
                foreach ($_FILES as $element => $value) {
                    if (is_array($value['tmp_name'])) {
                        foreach ($value['tmp_name'] as $key => $value) {
                            $file = array(
                                'name'      => $_FILES[$element]['name'][$key],
                                'type'      => $_FILES[$element]['type'][$key],
                                'tmp_name'  => $_FILES[$element]['tmp_name'][$key],
                                'error'     => $_FILES[$element]['error'][$key],
                                'size'      => $_FILES[$element]['size'][$key],
                            );

                            if ($result = self::validateFile($file, $uploaded)) {
                                $files[$element][$key] = $result;
                            }
                        }
                    } else {
                        if ($result = self::validateFile($value, $uploaded)) {
                            $files[$element] = $result;
                        }
                    }
                }
            }

            return $files;
        }
        

        /**
         * Validates that the file upload was successful and returns
         * the file data only if successful.
         *
         * @access public
         * @param array $file The file array to validate and format.
         * @param boolean $uploaded Whether the file has to have been uploaded
         * @return array The file data
         * @static
         */
        static public function validateFile($file, $uploaded = true)
        {
            if ($file['error']) {
                switch ($file['error']) {
                    case UPLOAD_ERR_INI_SIZE:
                    case UPLOAD_ERR_FORM_SIZE:
                        $error = \Phork::language()->translate('The file size exceeds the maximum allowed file size');
                        break;

                    case UPLOAD_ERR_PARTIAL:
                        $error = \Phork::language()->translate('The file was only partially uploaded');
                        break;

                    case UPLOAD_ERR_NO_TMP_DIR:
                        $error = \Phork::language()->translate('Invalid temporary directory');
                        break;

                    case UPLOAD_ERR_CANT_WRITE:
                        $error = \Phork::language()->translate('The file was not written to disk');
                        break;

                    default:
                        $error = \Phork::language()->translate('Undefined error');
                        break;
                }

                throw new \PhorkException(\Phork::language()->translate('There was an error uploading the file: %s', $error));
            } elseif ($uploaded && !is_uploaded_file($file['tmp_name'])) {
                throw new \PhorkException(\Phork::language()->translate('Invalid file - It must be an uploaded file'));
            }

            $file['tmp_name'] = realpath($file['tmp_name']);

            return $file;
        }
        

        /**
         * Moves the uploaded file from its temporary location to the
         * final location. If the file isn't an uploaded file it's 
         * copied to the new location rather than moved.
         *
         * @access public
         * @param string $tempPath The file's temporary location
         * @param string $filePath The filepath where the file should be saved
         * @param boolean $overwrite Whether the file can overwrite an existing file
         * @return boolean True if the file was saved successfully
         * @static
         */
        static public function saveFile($tempPath, $filePath, $overwrite = false)
        {
            if (!empty($filePath)) {
                if (($overwrite == true && is_file($filePath)) || !file_exists($filePath)) {
                    if (is_uploaded_file($tempPath)) {
                        if (!@move_uploaded_file($tempPath, $filePath)) {
                            throw new \PhorkException(\Phork::language()->translate('Unable to move uploaded file'));
                        }
                    } else {
                        if (!@copy($tempPath, $filePath)) {
                            $errors = \Phork::error()->getErrors();
                            $message = $errors->last();
                            throw new \PhorkException(\Phork::language()->translate('Unable to copy file'));
                        }
                    }
                } else {
                    throw new \PhorkException(\Phork::language()->translate('That file exists already and cannot be overwritten'));
                }
            } else {
                throw new \PhorkException(\Phork::language()->translate('Missing file path'));
            }

            return true;
        }
    }
