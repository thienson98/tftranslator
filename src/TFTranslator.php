<?php
/**
 * @package: 3F Translator
 * @author: Triệu Tài Niêm
 * @version: 1.0.0
 * @description: A small package help you auto generate texts and translate it to other languages. 
 */

namespace ThienSon98\TFTranslator;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class TFTranslator extends Command
{
    protected $signature = '3F:translator {--reload} {--justwrite} {--clear} {--auto} {--lang=}';

    protected $description = 'These command help you something... ahihi. Created by Trieu Tai Niem';

    private $json_path;
    
    private $justwrite = false;

    private $json_encode = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE;

    public function __construct() {
        $this->json_path = resource_path('lang/');
        //create folder if not exitst
        if(!file_exists($this->json_path))
            File::mkdir($this->json_path);

        parent::__construct();
    }

    public function handle() {

        $this->justwrite = $this->option('justwrite')?true:false;
        $langs = $this->getAvailableLanguages();
        
        //detect action
        if($this->option('reload')) {
            $this->resetTranslate($langs);
            exit();
        }

        if($this->option('clear')) {
            $this->removeUnusedKey();
            exit();
        }

        if($this->option('auto')) {
            $this->autoInsertTranslateFuncions();
        } 

        $this->updateTranslate($langs);
    }


    private function sanitizeArrayToTranslate($array) {
        $translate_string = [];
        foreach($array as $key) {
            $translate_string = array_merge($translate_string, explode('.', $key));
        }

        foreach($translate_string as $key => $str) {
            $translate_string[$key] = trim($str);
        }

        return array_filter($translate_string);
    }


    private function getAvailableLanguages() {
        $langs = $this->option('lang') ?? null;
        if(null !=$langs)
            $langs = explode(',', $langs);
        else {
            //get current language files
            $lang_files = File::files($this->json_path);
           
            foreach($lang_files as $file) {
                $lg = explode('.',basename($file));
                $langs[] = $lg[0];
            }

            if($langs === null)
                $langs = ['en', 'vi'];
        }

        return $langs;
    }

    private function scanStringInTranslateFunctions() {
        //get all views
        $files = File::allFiles(resource_path('views'));

        //define function for scan translate keys
        $merge_key = function ($content, $functionname, &$result) {
            preg_match_all("#$functionname\((.*?)\)#", $content, $key_matches);
            if(!empty($key_matches[1])) {
                foreach($key_matches[1] as $key) {
                    $current_text = substr($key, 1, -1);
                    if(!in_array($current_text, $result))
                        $result[] = $current_text;
                }
            }
        };

        $stranslate_keys = [];
        echo "+ Scanning views: ";
        foreach($files as $file) {
            $view_content = File::get($file);
            $merge_key($view_content, '__', $stranslate_keys);
            $merge_key($view_content, 'trans', $stranslate_keys);
            $merge_key($view_content, 'lang', $stranslate_keys);
        }
        echo "Success!\n";
        return $this->sanitizeArrayToTranslate($stranslate_keys);
    }

    
    private function scanTranslateTexts() {
        //get all views
        $files = File::allFiles(resource_path('views'));

        //define function for scan translate keys
        $merge_key = function ($content, &$result, $file) {
            //pattern for auto search translate text
            $regex_string = "#(([a-zA-Z0-9]| |\w+[.!\-,’&;'\?]\w?|[\"ÀÁÂÃÈÉÊÌÍÒÓÔÕÙÚĂĐĨŨƠàáâãèéêìíòóôõùúăđĩũơƯĂẠẢẤẦẨẪẬẮẰẲẴẶẸẺẼỀỀỂưăạảấầẩẫậắằẳẵặẹẻẽềềểỄỆỈỊỌỎỐỒỔỖỘỚỜỞỠỢỤỦỨỪễệỉịọỏốồổỗộớờởỡợụủứừỬỮỰỲỴÝỶỸửữựỳỵỷỹ])+(?=[<(\w.)>]))(<)#";
            preg_match_all($regex_string, $content, $key_matches);

            if(!empty($key_matches[1])) {
                foreach($key_matches[1] as $key) {
                    $key = trim($key);
                    if($key == '' || (int) $key != 0)
                        continue;
                    $result[$file][] = $key;
                }
            }

        };

        $stranslate_keys = [];
        echo "+ Scanning texts in views: ";
        foreach($files as $file) {
            $view_content = File::get($file);
            $merge_key($view_content, $stranslate_keys, (string)$file);
        }
        echo "completed!\n";
        return $stranslate_keys;
    }

    /**
     * @return: mixed, if success will be return array of string translated
     */
    private function callApiToTranslateString($language, $string_keys) {
        $string_keys = $this->sanitizeArrayToTranslate($string_keys);

        if( $this->justwrite) {
            return $string_keys;
        } else {
            echo "\t- Translate to ".$language.': ';

            $translate_string = implode("\n", $string_keys);
            $translate_string = urlencode($translate_string);

            //google translate API
            $api_connect_string = "https://translate.googleapis.com/translate_a/single?client=gtx&dt=t&ie=UTF-8&oe=UTF-8&sl=auto&tl=$language&q=".$translate_string;

            $cURL_Api = curl_init();
            curl_setopt_array($cURL_Api, array(
                CURLOPT_URL => $api_connect_string,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "GET",
                CURLOPT_HTTPHEADER => array(
                    "cache-control: no-cache"
                )
            ));

            sleep(2);
            $translate_res = curl_exec($cURL_Api);

            curl_close($cURL_Api);
            
            //check if translate is success
            if($translate_res) {
                $translate_res = json_decode($translate_res, true);
                //$translate_res = $translate_res['text'][0];
                if(isset($translate_res[0])) {

                    if(count($translate_res[0]) != count($string_keys)) {
                        echo "[Warning] Can't translate string from \"$translate_res[2]\" to \"$language\"\n";
                        return $string_keys;
                    }

                    echo "completed!\n";

                    $res_arr = array_column($translate_res[0],0);
                    $return_arr = [];

                    foreach($res_arr as $string) {
                        $return_arr[] = trim($string);
                    }

                    return $return_arr;
                }
                else {
                    echo "Error when traslate string!\n\n";
                    die();
                }
            } else {
                echo "Error when traslate string!\n\n";
                die();
            }
        }

    }

    
    private function writeTranslateStringKey($language, $stranslate_keys) {
        
        //generate string array
        $json_string = [];

        $string_translated = $this->callApiToTranslateString($language, $stranslate_keys);
    
        $i = 0;
        foreach($stranslate_keys as $key) {
            $json_string[$key] = trim($string_translated[$i++]);
        }

        $json_string = json_encode($json_string,$this->json_encode);
        File::put($this->json_path."$language.json", $json_string);
    }


    private function updateTransalteString($language, $stranslate_keys) {
        //get old string keys
        $old_json_strings = [];
        if(file_exists($this->json_path."$language.json")) {
            $old_json_strings = File::get($this->json_path."$language.json");
            $old_json_strings = json_decode($old_json_strings, true);
        }

        //generate array string
        $new_array_strings = [];
        foreach($stranslate_keys as $string_key) {
            //translate string
            if(!array_key_exists($string_key, $old_json_strings))  {
                $new_array_strings[] = $string_key;
            }
        }
        
        if(empty($new_array_strings)) {
            echo "\t** Nothing to update!\n";
        } else {
            
            $string_translated = $this->callApiToTranslateString($language, $new_array_strings);
                        
            $i = 0;
            foreach($new_array_strings as $string_key) {
                $old_json_strings[$string_key] = $string_translated[$i++];
            }

            $json_string = json_encode($old_json_strings, $this->json_encode);
            File::put($this->json_path."$language.json", $json_string);
        }
    }


    private function resetTranslate($langs) {
        echo "\nRESET ALL TRANSALTE STRINGS\n";
        echo "\tWarning: You will reset all translate strings, include these string are translated.\n";
        echo "\tLanguages file(s) will be reset: ". implode('.json ',$langs). ".json\n\t";
        $cmd = readline("Do you want to continue? (y/n): ");
        if($cmd != 'y')
            die("\tCanceled!");

        //get all translate strings
        $stranslate_keys = $this->scanStringInTranslateFunctions();
        foreach($langs as $lg) {
            echo "+ Writting $lg.json: \n";
            $this->writeTranslateStringKey($lg, $stranslate_keys);   
            echo "\t- Updated file $lg.json\n";
        }
        echo "---------------> Done ^_^ \n\n";
    }

    
    private function updateTranslate($langs) {        
        echo "\nUPDATE TRANSALTE STRINGS\n";
        //get all translate strings
        $stranslate_keys = $this->scanStringInTranslateFunctions();
        //update translate
        foreach($langs as $lg) {
            echo "+ Updating $lg.json: \n";
            $this->updateTransalteString($lg, $stranslate_keys);
        }
        echo "---------------> Done ^_^ \n\n";
    }

    private function removeUnusedKey() {
        echo "REMOVE UNUSED STRING KEYS\n";
        $all_string_keys = $this->scanStringInTranslateFunctions();
        $available_langs = $this->getAvailableLanguages();
        
        foreach($available_langs as $lang) {
            echo "+ Cleaning $lang.json: \n";
            $current_lang_keys = File::get($this->json_path."$lang.json");
            $current_lang_keys = json_decode($current_lang_keys, true);
            if(!is_array($current_lang_keys)) continue;

            $removed = [];

            foreach($current_lang_keys as $key => $string) {
                if(!in_array($key,$all_string_keys)) {
                    $removed[] = $key;
                    unset($current_lang_keys[$key]);
                }
            }

            if(count($removed) > 0) {
                echo "\t Removed: ".implode(", ", $removed)."\n";
                File::put($this->json_path."$lang.json", json_encode($current_lang_keys, $this->json_encode));
            } else {
                echo "\tNo need to clean!\n";
            }
        }
    }


    private function autoInsertTranslateFuncions() {
        $translate_strings = $this->scanTranslateTexts();
        echo "+ Insert translate function to views files: \n";

        if(empty($translate_strings))  {
            echo "\t ** Nothing to update!\n\n";
            return;
        }

        foreach($translate_strings as $file => $strings) {
            $file_content = File::get($file);
            echo "\t - ".basename($file).": ";
            $arr_search = [];
            $arr_replace = [];
            foreach($strings as $str) {
                if(!in_array($str, $arr_search)) {
                    $arr_replace[] = "> {{__('".$str."')}} <";
                    $arr_search[] = "#>\s*$str\s*\W*<#";
                }
            }

            $file_content = preg_replace($arr_search, $arr_replace, $file_content);
            File::put($file, $file_content);

            echo "OK! \n";
        }
    }

}
