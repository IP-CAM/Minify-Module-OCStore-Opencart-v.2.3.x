<?php
require_once 'minify/cssmin.class.php';
require_once 'minify/jsmin.class.php';

class ControllerExtensionModuleMinify extends Controller {

    // время хранения файла в секундах
    private $file_time_expired = '3600';
	
    // массив списка js файлов со страницы
    private $js_array = [];

    // массив списка css файлов со страницы
    private $css_array = [];
    
    // путь до js файла
    private $output_js;

    // путь до css файла
    private $output_css;

    // путь до дериктории
    private $out_folder;

    // Gzip
    private $gzip;

    // основная функция запуска
    public function minify () {
        
        if(!$this->config->get('minify_status')) return false;
        $this->gzip = $this->config->get('minify_gzip');
        
        $this->jgz = $this->gzip ? '.jgz' : '';
        
        $this->file_time_expired = $this->config->get('minify_time') ? $this->config->get('minify_time') : $this->file_time_expired;
		
        // получаем папку темы
        if ($this->config->get('config_theme') == 'theme_default') {
            $theme = $this->config->get('theme_default_directory');
        } else {
            $theme = $this->config->get('config_theme');
        }

        // указываем путь относительно темы
        $this->out_folder = 'catalog/view/theme/' . $theme . '/minify';
        unset($theme);

        // получаем html
        $buffer = $this->response->getOutput();
		
        // проверяем существование директории
        $this->check_path($this->out_folder);

        // собираем стили
        if($this->config->get('minify_css')) $buffer = $this->css($buffer);

        // собираем скрипты
        if($this->config->get('minify_js')) $buffer = $this->js($buffer);
				
        // собираем js из контента, сжимаем html и js
        if($this->config->get('minify_html')) $buffer = $this->html($buffer);
		
		// вставляем наши новые файлы в конец тега head
        $string = '<link href="/' . $this->output_css . '" type="text/css" rel="stylesheet" /><script src="/' . $this->output_js . '" type="text/javascript"></script></head>';
		$buffer = str_replace('</head>', $string, $buffer);
		unset($string);
		
		// рендерим новый html
        $this->response->setOutput($buffer);
    }

    // сжимаем собранные js или css в переменную
    private function concatFiles($type) {
        
        switch ($type) {
            case 'css':
                $this->css_array = $this->css_array;
                $css = $this->accept_array($this->css_array, 'css');
				$minify = CSSMin::minify($css);
                $output_file = $this->output_css;
                unset($css);
                break;
            case 'js':
                $this->js_array = $this->js_array;
                $js = $this->accept_array($this->js_array);
                $minify = JSMin::minify($js);
                $minify = $js;
                $output_file = $this->output_js;
                unset($js);
                break;
        }		

        if (empty($minify) || empty($output_file)) return false;

        $minify = $this->gzip ? gzencode($minify) : $minify;
        $result = file_put_contents($output_file, $minify);

        unset($type,$minify,$output_file);
        return $result;
    }

    // проверяем существования пути
    private function check_path($path) {
        if (!file_exists($path)) {
            mkdir($path);
        }

        if (!is_readable($path)) {
            trigger_error('Directory for compressed assets is not readable.');
        }

        if (!is_writable($path)) {
            trigger_error('Directory for compressed assets is not writable.');
        }
        unset($path);
    }
    
    private function accept_array($array, $type = false) {
        $data = '';
        foreach ($array as &$item) {
            $file = file_get_contents($item) . PHP_EOL;
            if ($type === 'css') {
                $file = preg_replace('#url\((?!\s*[\'"]?(data\:image|/|http([s]*)\:\/\/))\s*([\'"])?#i', "url($3{$this->getPath($item)}", $file);
            }
            $data .= $file;
            unset($item,$file);
        }
        unset($array);
        return $data;
    }
    
    private function file_check($file) {
        if (is_file($file)) {
            $time = time() - filemtime($file);
            unset($file);
            if ($time > $this->file_time_expired) {
                return true;
            } else {
                return false;
            }
        } else {
            return true;
        }
    }
    
    // собираем css
    private function css($buffer) {
        preg_match_all('/<link[^>]*href="([^"]*).css"[^>]*>/is', $buffer, $styles);
        foreach ($styles['0'] as &$style) {
            preg_match('/^<link.*?href=(["\'])(.*?)\1.*$/', $style, $style_name);
            $file_name = $style_name['2'];
            if (!empty($file_name)) {
                if (is_readable($file_name)) {
                    if(stristr($style_name['2'],'//')) {
                        if(stristr($style_name['2'],$_SERVER['SERVER_NAME']))
                            $this->css_array[] = $file_name;
                    } else {
                        $this->css_array[] = $file_name;
                    }
                }
            }
            unset($style,$style_name,$file_name);
        }

        // удаляем старые стили
        $buffer = preg_replace('/<link[^>]*href="([^"]*).css"[^>]*>\n/is', '', $buffer);
        $_css_file = md5(serialize($this->css_array)) . '.css' . $this->jgz;
        $this->output_css = $this->out_folder . '/' . $_css_file;
        if ($this->file_check($this->output_css)) {
            $this->concatFiles('css');
        }

        unset($styles,$_css_file);
        return $buffer;
    }
    
    // собираем js
    private function js($buffer) {
        preg_match_all('/<script\b[^>]*><\/script>/is', $buffer, $scripts);
        foreach ($scripts['0'] as &$script) {
            preg_match('/src=(["\'])(.*?)\1/', $script, $script_name);
            $file_name = $script_name['2'];
            if (!empty($file_name)) {
                if (is_readable($file_name)) {
                    if(stristr($script_name['2'],'//')) {
                        if(stristr($script_name['2'],$_SERVER['SERVER_NAME'])) {
                            $this->js_array[] = $file_name;
                        }
                    } else {
                        $this->js_array[] = $file_name;
                    }
                }
            }

            unset($script,$script_name,$file_name);
        }

        // удаляем старые скрипты
        $buffer = preg_replace('/<script\b[^>]*><\/script>\n/is', '', $buffer);
        $_js_file = md5(serialize($this->js_array)) . '.js' . $this->jgz;
        $this->output_js = $this->out_folder . '/' . $_js_file;
        if ($this->file_check($this->output_js)) {
            $this->concatFiles('js');
        }
        
        unset($scripts,$_js_file);
        return $buffer;
    }

    // собираем js из контента, сжимаем html и js
    private function html($buffer) {
        preg_match_all('/<script>(.*?)<\/script>/is', $buffer, $html_js_1);
        preg_match_all('/<script type="text\/javascript">(.*?)<\/script>/is', $buffer, $html_js_2);
        $html_js = array_merge($html_js_1['1'], $html_js_2['1']);
        foreach ($html_js as $i => &$js){
            if(!empty($js)) {
                $search = ["<script>". $js ."</script>","<script type=\"text/javascript\">". $js ."</script>"];
                $buffer = str_replace($search, '<script data-s="' . $i . '" type="text/javascript">' . $js . '</script>', $buffer);
                unset($search);
            }
            unset($js,$i);
        }

        // сжимаем html
        $buffer= preg_replace('|\s+|', ' ', $buffer);

        // возвращаем js на место
        foreach ($html_js as $i => &$js) {
            $js = JSMin::minify($js);
            $buffer = preg_replace('/<script data-s="' . $i . '" type="text\/javascript">(.*?)<\/script>/is', '<script type="text/javascript">' . $js . '</script>', $buffer);
            unset($js,$i);
        }
        
        unset($html_js,$html_js_1,$html_js_2);
        return $buffer;
    }

    // исправляем путь до файлов прописанные в url() css
    private function getPath($file){
        if(empty($file)) return '';
        $outFile = dirname($file) . "/";
        $outFile = '/' . str_replace($this->out_folder, '/', $outFile) . '/';
        $outFile = str_replace('//', '/', $outFile);
        unset($file);
        return $outFile;
    }
}