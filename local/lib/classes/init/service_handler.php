<?php

namespace Local\Init;

use Bitrix\Main\Diag\Helper;
use CFile;

/**
 * Class ServiceHandler
 * Class with custom service function for development, testing and using in project
 *
 * @package Local\Init
 */
class ServiceHandler
{
    private const LOG_FILENAME = "local/logs/custom-log.txt";

    /**
     * Method write debug information into log file
     *
     * @param mixed $sText Entity to write
     * @param string $log_file Relative path from DOCUMENT_ROOT to log file (optional, default empty string)
     * @param string $addInfo Additional info about given variable (optional, default empty string). For short log
     *     version give 'short_version'
     * @param int $traceDepth Depth of trace (optional, default 1)
     * @param bool $bShowArgs Show arguments of traced functions (optional, default false)
     */
    public static function writeToLog($sText, string $log_file = '', string $addInfo = '', int $traceDepth = 1, bool $bShowArgs = false): void
    {
        $type = gettype($sText);
        if (!is_string($sText)) {
            $sText = var_export($sText, true);
        }

        $fileName = (strlen(trim($log_file)) > 0) ? $log_file : self::LOG_FILENAME;

        if ((strlen($fileName) > 0 && strlen($sText) > 0)) {

            ignore_user_abort(true);

            if ($fp = @fopen($_SERVER["DOCUMENT_ROOT"] . "/" . $fileName, "ab")) {
                if (flock($fp, LOCK_EX)) {
                    $header = "Date: " . date("d.m.Y, H:i:s") . "\n";
                    if ('short_version' !== $addInfo) {
                        $header .= mb_strlen($addInfo) > 0 ? "* Additional info: " . $addInfo . "\n" : '';
                        $header .= mb_strlen($type) > 0 ? "* Entity type: " . $type . "\n" : '';
                        $header .= mb_strlen(debug_backtrace()[1]['function']) > 0 ? "* Caller: " . debug_backtrace()[1]['function'] . "\n" : '';
                        $header .= "\n" . $sText . "\n";
                    } else {
                        $header .= $sText . "\n";
                    }

                    @fwrite($fp, $header);

                    $arBacktrace = Helper::getBackTrace($traceDepth, ($bShowArgs ? null : DEBUG_BACKTRACE_IGNORE_ARGS));
                    $strFunctionStack = $strFilesStack = '';
                    $iterationsCount = min(count($arBacktrace), $traceDepth);

                    for ($i = 1; $i < $iterationsCount; $i++) {
                        $strFunctionStack .= strlen($strFunctionStack) > 0 ? " < " : '';
                        $strFunctionStack .= isset($arBacktrace[$i]["class"]) ? $arBacktrace[$i]["class"] . "::" : '';
                        $strFunctionStack .= $arBacktrace[$i]["function"];
                        $strFilesStack .= isset($arBacktrace[$i]["file"]) ? "\t" . $arBacktrace[$i]["file"] . ":" . $arBacktrace[$i]["line"] . "\n" : '';

                        if ($bShowArgs && isset($arBacktrace[$i]["args"])) {
                            $strFilesStack .= "\t\t";
                            $strFilesStack .= isset($arBacktrace[$i]["class"]) ? $arBacktrace[$i]["class"] . "::" : '';
                            $strFilesStack .= $arBacktrace[$i]["function"];
                            $strFilesStack .= "(\n";

                            foreach ($arBacktrace[$i]["args"] as $value) {
                                $strFilesStack .= "\t\t\t" . $value . "\n";
                            }

                            $strFilesStack .= "\t\t)\n";
                        }
                    }

                    if (strlen($strFunctionStack) > 0) {
                        @fwrite($fp, "    " . $strFunctionStack . "\n" . $strFilesStack);
                    }

                    if ('short_version' !== $addInfo) {
                        @fwrite($fp, "--------------------------\n\n\n");
                    } else {
                        @fwrite($fp, "\n");
                    }

                    @fflush($fp);
                    @flock($fp, LOCK_UN);
                    @fclose($fp);
                }
            }
            ignore_user_abort(false);
        }
    }

    /**
     * Method prints PHP variable to browser console
     *
     * @param mixed $var - Variable to print
     * @param string $additionalInfo - Additional information about $var (optional, default empty string)
     * @param boolean $warn - show in console.warn instead of console.log (optional, default false)
     *
     * @return boolean true|false
     */
    public static function writeToConsole($var, $additionalInfo = '', $warn = false)
    {
        $printString = '';
        $varJson = json_encode(trim($var));
        if (0 < strlen($varJson)) {
            $additionalInfo = trim($additionalInfo);
            if (0 < strlen($additionalInfo)) {
                $printString = '<script type="text/javascript">console.' . ($warn ? 'warn' : 'log') . '("↓ ' . $additionalInfo . ' ↓");</script>';
            }
            $printString .= '<script type="text/javascript">console.' . ($warn ? 'warn' : 'log') . '(' . $varJson . ')</script>';
        } else {
            return false;
        }

        echo $printString;

        return true;
    }

    /**
     * Method prints value of given variable inside 'pre' tags
     *
     * @param mixed $var Variable to print
     * @param string $additionalInfo Additional info about $var (optional, default empty string)
     * @param boolean $displayNone Echo $var in style display: none (optional, default false)
     * @param string $displayNoneClass Custom class for display none div tag (optional, default empty string)
     *
     * @return boolean true
     */
    public static function customVarDump($var, $additionalInfo = '', $displayNone = false, $displayNoneClass = '')
    {
        $outputString = '';
        if (gettype($var) !== null) {
            $outputString .= "<pre>";
            if (0 < mb_strlen($additionalInfo)) {
                $outputString .= "<p style='font-weight: bold'>&darr;&nbsp;{$additionalInfo}&nbsp;&darr;</p>";
            }
            $outputString .= var_export($var, true);
            $outputString .= "</pre>";
        } else {
            $outputString .= "<pre><h3>Given var is null!</h3></pre>";
        }

        if ($displayNone) {
            echo "<div style=\"display:none\" class=\"{$displayNoneClass}\">{$outputString}</div>";
        } else {
            echo $outputString;
        }

        return true;
    }

    /**
     * Method converts and resizes image from webp to jpeg
     *
     * @param int $bitrixImageId Image id in bitrix image table
     * @param int $resizeWidth Resize width in pixels (default 0)
     * @param int $resizeHeight Resize height in pixels (default 0)
     * @param int $quality JPEG convert quality (max 100, 75 default)
     *
     * @return array|bool Array with image info: ['SRC', 'WIDTH', 'HEIGHT', 'SIZE']
     */
    public static function convertFromWebpToJpeg(int $bitrixImageId, int $resizeWidth = 0, int $resizeHeight = 0, int $quality = 75): array
    {
        if (0 >= $bitrixImageId) {
            return false;
        }

        $arImageInfo = CFile::GetFileArray($bitrixImageId);

        if ($resizeWidth < intval($arImageInfo['WIDTH']) || $resizeHeight < intval($arImageInfo['HEIGHT'])) {
            $arResizedImageInfo = CFile::ResizeImageGet($bitrixImageId, [
                    'width'  => $resizeWidth,
                    'height' => $resizeHeight
                ], BX_RESIZE_IMAGE_PROPORTIONAL, false);
            [$width, $height, $type, $attr] = getimagesize($_SERVER['DOCUMENT_ROOT'] . $arResizedImageInfo['src']);
            $arImageInfo['SRC'] = $arResizedImageInfo['src'];
            $arImageInfo['WIDTH'] = $width;
            $arImageInfo['HEIGHT'] = $height;
            $arImageInfo['SIZE'] = filesize($_SERVER['DOCUMENT_ROOT'] . $arResizedImageInfo['src']);
        }

        if ('image/webp' === strval($arImageInfo['CONTENT_TYPE'])) {
            $fileName = substr($arImageInfo['FILE_NAME'], 0, strpos($arImageInfo['FILE_NAME'], '.')) . '_.jpeg';
            $filePath = substr($arImageInfo['SRC'], 0, strrpos($arImageInfo['SRC'], '/')) . '/' . $fileName;
            $fullFilePath = $_SERVER['DOCUMENT_ROOT'] . $filePath;

            if (!file_exists($fullFilePath)) {
                $oWebpImage = imagecreatefromwebp($_SERVER['DOCUMENT_ROOT'] . $arImageInfo['SRC']);
                imagejpeg($oWebpImage, $fullFilePath, $quality);
                imagedestroy($oWebpImage);
            }

            $arImageInfo['SRC'] = $filePath;
            $arImageInfo['SIZE'] = filesize($fullFilePath);
        }

        return [
            'SRC'    => $arImageInfo['SRC'],
            'WIDTH'  => $arImageInfo['WIDTH'],
            'HEIGHT' => $arImageInfo['HEIGHT'],
            'SIZE'   => $arImageInfo['SIZE']
        ];
    }


    /**
     * Method converts and resizes image from jpeg to webp
     *
     * @param int $bitrixImageId Image id in bitrix image table
     * @param int $resizeWidth Resize width in pixels (default 0)
     * @param int $resizeHeight Resize height in pixels (default 0)
     * @param int $quality WEBP convert quality (max 100, 75 default)
     *
     * @return array|bool Array with image info: ['ID', 'SRC', 'WIDTH', 'HEIGHT', 'SIZE']
     */
    public static function convertFromJpegToWebp(int $bitrixImageId, int $resizeWidth = 0, int $resizeHeight = 0, int $quality = 75): array
    {
        if (0 >= $bitrixImageId) {
            return false;
        }

        $arImageInfo = CFile::GetFileArray($bitrixImageId);

        if ($resizeWidth < intval($arImageInfo['WIDTH']) || $resizeHeight < intval($arImageInfo['HEIGHT'])) {
            $arResizedImageInfo = CFile::ResizeImageGet($bitrixImageId, [
                    'width'  => $resizeWidth,
                    'height' => $resizeHeight
                ], BX_RESIZE_IMAGE_PROPORTIONAL, false);
            [$width, $height, $type, $attr] = getimagesize($_SERVER['DOCUMENT_ROOT'] . $arResizedImageInfo['src']);
            $arImageInfo['SRC'] = $arResizedImageInfo['src'];
            $arImageInfo['WIDTH'] = $width;
            $arImageInfo['HEIGHT'] = $height;
            $arImageInfo['SIZE'] = filesize($_SERVER['DOCUMENT_ROOT'] . $arResizedImageInfo['src']);
        }

        if ('image/jpeg' === strval($arImageInfo['CONTENT_TYPE'])) {
            $fileName = substr($arImageInfo['FILE_NAME'], 0, strpos($arImageInfo['FILE_NAME'], '.')) . '.webp';
            $filePath = substr($arImageInfo['SRC'], 0, strrpos($arImageInfo['SRC'], '/')) . '/' . $fileName;
            $fullFilePath = $_SERVER['DOCUMENT_ROOT'] . $filePath;

            if (!file_exists($fullFilePath)) {
                $oJpegImage = imagecreatefromjpeg($_SERVER['DOCUMENT_ROOT'] . $arImageInfo['SRC']);
                imagewebp($oJpegImage, $fullFilePath, $quality);
                imagedestroy($oJpegImage);
            }

            $arImageInfo['SRC'] = $filePath;
            $arImageInfo['SIZE'] = filesize($fullFilePath);
        }

        return [
            'ID'     => $arImageInfo['ID'],
            'SRC'    => $arImageInfo['SRC'],
            'WIDTH'  => $arImageInfo['WIDTH'],
            'HEIGHT' => $arImageInfo['HEIGHT'],
            'SIZE'   => $arImageInfo['SIZE']
        ];
    }


    /**
     * Method removes BOM sequence from string beginnings
     *
     * @param string $string
     *
     * @return string
     */
    public static function removeBOM(string $string): string
    {
        if (0 === strpos(bin2hex($string), 'efbbbf')) {
            return substr($string, 3);
        }

        return $string;
    }
}
