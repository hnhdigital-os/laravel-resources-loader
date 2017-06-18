<?php

namespace Bluora\LaravelResourcesLoader;

use Config;
use Roumen\Asset\Asset;

class Resource
{
    private static $containers = [];

    /**
     * Get the version of the given resource.
     *
     * @return string
     */
    public function version($name, $version = false)
    {
        return (empty($version)) ? Config::get('resource.'.$name.'.1') : $version;
    }

    /**
     * Track loaded inline files.
     *
     * @var array
     */
    private $loaded_inline = [];

    /**
     * Add the asset using our version of the exliser loader.
     *
     * @param string $file
     * @param string $params
     * @param bool   $onUnknownExtension
     *
     * @return void
     */
    public function add($file, $params = 'footer', $onUnknownExtension = false)
    {
        Asset::add($this->elixir($file), $params, $onUnknownExtension);
    }

    /**
     * Add raw script to page.
     *
     * @param string $style
     * @param string $params
     *
     * @return void
     */
    public function addScript($script, $params = 'footer')
    {
        Asset::addScript($script, $params);
    }

    /**
     * Reverse the order of scripts.
     *
     * @param string $params
     *
     * @return void
     */
    public function reverseStylesOrder($params = 'footer')
    {
        if (isset(Asset::$scripts[$params])) {
            Asset::$scripts[$params] = array_reverse(Asset::$scripts[$params], true);
        }
    }

    /**
     * Add raw styling to page.
     *
     * @param string $style
     * @param string $params
     *
     * @return void
     */
    public function addStyle($style, $params = 'header')
    {
        Asset::addStyle($style, $params);
    }

    /**
     * Add the asset first using our version of the exliser loader.
     *
     * @param string $file
     * @param string $params
     * @param bool   $onUnknownExtension
     *
     * @return return string
     */
    public function addFirst($file, $params = 'footer', $onUnknownExtension = false)
    {
        Asset::addFirst($this->elixir($file), $params, $onUnknownExtension);
    }

    /**
     * Add new asset after another asset in its array.
     *
     * @param string       $file1
     * @param string       $file2
     * @param string|array $params
     * @param bool         $onUnknownExtension
     *
     * @return void
     */
    public function addAfter($file1, $file2, $params = 'footer', $onUnknownExtension = false)
    {
        Asset::addAfter($this->elixir($file1), $this->elixir($file2), $params, $onUnknownExtension);
    }

    /**
     * Return CSS.
     *
     * @param array $arguments
     *
     * @return string
     */
    public function css(...$arguments)
    {
        return Asset::css(...$arguments);
    }

    /**
     * Return LESS.
     *
     * @param array $arguments
     *
     * @return string
     */
    public function less(...$arguments)
    {
        return Asset::less(...$arguments);
    }

    /**
     * Return styles.
     *
     * @param array $arguments
     *
     * @return string
     */
    public function styles(...$arguments)
    {
        return Asset::styles(...$arguments);
    }

    /**
     * Return javascript.
     *
     * @param array $arguments
     *
     * @return string
     */
    public function js(...$arguments)
    {
        return Asset::js(...$arguments);
    }

    /**
     * Return scripts.
     *
     * @param array $arguments
     *
     * @return string
     */
    public function scripts(...$arguments)
    {
        return Asset::scripts(...$arguments);
    }

    /**
     * Add new asset after another asset in its array.
     *
     * @param array $container_settings
     *
     * @return void
     */
    public static function container($container_settings)
    {
        self::loadContainer($container_settings);
    }

    /**
     * Load an assets container (it will load the individual files).
     *
     * @param array $arguments
     *
     * @return void
     */
    public function containers(...$arguments)
    {
        if (isset($arguments[0])) {
            $container_list = $arguments[0];
            foreach ($container_list as $container_settings) {
                $this->loadContainer($container_settings);
            }
        }
    }

    /**
     * Load an assets container (it will load the individual files).
     *
     * @param array $asset_settings
     *
     * @return void
     */
    private static function loadContainer($class_settings)
    {
        if (is_array($class_settings)) {
            $asset_name = array_shift($class_settings);
        } else {
            $asset_name = $class_settings;
            $class_settings = [];
        }

        $class_name = false;

        if ($asset_details = config('resource.'.$asset_name, false)) {
            $class_name = array_get($asset_details, 0, false);
        }

        if ($class_name !== false && !isset(self::$containers[$class_name])) {
            self::$containers[$class_name] = new $class_name(...$class_settings);
        }
    }

    /**
     * Load local files for a given controller.
     *
     * @param array  $file_extensions
     * @param string $file
     *
     * @return void
     */
    public function controller($file_extensions, $file)
    {
        if (!is_array($file_extensions)) {
            $file_extensions = [$file_extensions];
        }
        $manifest = config('rev-manifest', []);
        foreach ($file_extensions as $extension) {
            $file_name = str_replace('.', '/', $file).'.'.$extension;

            if (env('APP_ENV') == 'local') {
                $file_path = dirname(resource_path().'/views/'.$file_name);
                $file_path .= '/'.$extension.'/'.basename($file_name);
            } else {
                $file_path = public_path().'/assets/'.$file_name;
            }

            if (isset($manifest[$file_name]) || file_exists($file_path)) {
                if (env('APP_ENV') == 'local') {
                    if (!isset($this->loaded_inline[$file_path])) {
                        $contents = file_get_contents($file_path);
                        $contents = '/* '.$file_name." */ \n\n".$contents;
                        if ($extension == 'js') {
                            $this->addScript($contents, 'inline');
                        } else {
                            $this->addStyle($contents);
                        }
                        $this->loaded_inline[$file_path] = true;
                    }
                } else {
                    $this->add($file_name, 'ready');
                }
            }
        }
    }

    /**
     * Override standard elixir to return standard url if
     * the exception is made (eg the file isn't versioned).
     *
     * @param string $file
     *
     * @return return string
     */
    public function elixir($file)
    {
        if (substr($file, 0, 4) === 'http') {
            return $file;
        }
        try {
            if (env('APP_ASSET_SOURCE', 'build') === 'build') {
                $elixir_path = elixir($file);

                return $elixir_path;
            }

            return '/'.env('APP_ASSET_SOURCE').'/'.$file;
        } catch (\InvalidArgumentException $e) {
            if (file_exists(public_path().'/'.$file)) {
                return $file;
            } elseif (file_exists(public_path().'/assets/'.$file)) {
                return '/assets/'.$file;
            }
        }

        return '';
    }

    public static function http2()
    {
        foreach (Asset::$css as $file) {
            header('Link: <'.$file.'>; rel=preload; as=style;', false);
        }
        foreach (Asset::$js as $section) {
            foreach ($section as $file) {
                header('Link: <'.$file.'>; rel=preload; as=script;', false);
            }
        }
    }
}
