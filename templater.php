<?php
class TemplateEngine {
    protected $variables = array();
    protected $templatePath = '';

    public function __construct($templatePath = '') {
        $this->templatePath = $templatePath;
    }

    public function assign($key, $value) {
        $this->variables[$key] = $value;
    }

    public function render($template) {
        $output = file_get_contents($this->templatePath . $template);
    
        // Замена переменных в шаблоне
        foreach ($this->variables as $key => $value) {
            // Если значение переменной - массив, заменяем его на строку с элементами массива
            if (is_array($value)) {
                $output = $this->replaceArrayVariable($output, $key, $value);
            } else {
                $output = str_replace('{{ $' . $key . ' }}', $value, $output);
            }
        }
    
        // Замена конструкции {% foreach () %} {% endforeach %}
        $output = preg_replace_callback('/{%\s*foreach\s*\((.*?)\s+as\s+(.*?)\)\s*%}(.*?)\s*{%\s*endforeach\s*%}/s', function ($matches) {
            $code = '';
            // Извлекаем переменные из текущего контекста
            extract($this->variables);
            // Создаем временный массив
            eval('$temp_array = ' . $matches[1] . ';');
            // Выполняем оператор foreach во временном массиве
            foreach ($temp_array as $item) {
                // Извлекаем переменные из текущего контекста
                // Заменяем переменные внутри цикла
                $code .= preg_replace_callback('/{{\s*\$([^}]*)\s*}}/', function ($match) use ($item) {
                    $variable = $match[1];
                    if (strpos($variable, '[\'') !== false) {
                        $parts = explode('[\'', $variable);
                        $index = str_replace('\'] ', '', $parts[1]);
                    }
                    if (isset($item[$index])) {
                        return $item[$index];
                    }
                    return '';
                }, $matches[3]);
            }
            return $code;
        }, $output);
        
        // Замена конструкции {% if () %} {% else %} {% endif %}
        $output = preg_replace_callback('/{%\s*if\s*\((.*?)\)\s*%}(.*?){% else %}(.*?){%\s*endif\s*%}/s', function ($matches) {
            // Извлекаем переменные из текущего контекста
            extract($this->variables);
            // Выполняем оператор if
            eval('$condition = ' . $matches[1] . ';');
            if ($condition) {
                return $matches[2];
            } else {
                return $matches[3];
            }
        }, $output);
        
        // Замена конструкции {% if () %} {% endif %}
        $output = preg_replace_callback('/{%\s*if\s*\((.*?)\)\s*%}(.*?){%\s*endif\s*%}/s', function ($matches) {
            // Извлекаем переменные из текущего контекста
            extract($this->variables);
            // Выполняем оператор if
            eval('$condition = ' . $matches[1] . ';');
            if ($condition) {
                return $matches[2];
            }
        }, $output);
    
        return $output;
    }
    
    private function replaceArrayVariable($output, $key, $value) {
        // Проходим по каждому элементу вложенного массива
        foreach ($value as $subKey => $subValue) {
            // Если значение вложенного массива - массив, рекурсивно вызываем этот метод
            if (is_array($subValue)) {
                return $this->replaceArrayVariable($output, $key, $subValue);
            } else {
                // Если значение вложенного массива - не массив, заменяем в шаблоне
                $output = str_replace('{{ $' . $key . '[\'' . $subKey . '\'] }}', $subValue, $output);
            }
        }
        return $output;
    }
}