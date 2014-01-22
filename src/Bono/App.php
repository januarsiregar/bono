<?php

/**
 * Bono - PHP5 Web Framework
 *
 * MIT LICENSE
 *
 * Copyright (c) 2013 PT Sagara Xinix Solusitama
 *
 * Permission is hereby granted, free of charge, to any person obtaining
 * a copy of this software and associated documentation files (the
 * "Software"), to deal in the Software without restriction, including
 * without limitation the rights to use, copy, modify, merge, publish,
 * distribute, sublicense, and/or sell copies of the Software, and to
 * permit persons to whom the Software is furnished to do so, subject to
 * the following conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
 * MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE
 * LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION
 * OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION
 * WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 *
 * @author      Ganesha <reekoheek@gmail.com>
 * @copyright   2013 PT Sagara Xinix Solusitama
 * @link        http://xinix.co.id/products/bono
 * @license     https://raw.github.com/xinix-technology/bono/master/LICENSE
 * @package     Bono
 *
 */
namespace Bono;

use Slim\Slim;
use Bono\Provider\ProviderRepository;
use ROH\Util\Inflector;

/**
 * App
 * Bono default application context
 *
 */
class App extends Slim {

    /**
     * Application context state whether it is running or not
     * @var boolean
     */
    protected $isRunning = false;

    /**
     * Override default settings
     * @return array
     */
    public static function getDefaultSettings() {
        $settings = parent::getDefaultSettings();

        $settings['templates.path'] = '../templates';
        $settings['config.path'] = '../config';
        $settings['debug'] = false;
        $settings['autorun'] = true;
        $settings['bono.debug'] = true;
        $settings['view'] = '\\Bono\\View\\LayoutedView';
        return $settings;
    }

    /**
     * Constructor
     * @param array $userSettings override settings from parameter
     */
    public function __construct(array $userSettings = array()) {

        parent::__construct($userSettings);

        $this->container->singleton('request', function ($c) {
            return new \Bono\Http\Request($c['environment']);
        });

        $this->container->singleton('_config', function ($c) {
            return new \Bono\Config\Config();
        });

        $this->container->singleton('response', function ($c) {
            return new \Bono\Http\Response();
        });

        $this->configureHandler();

        $this->configure();

        $this->configureProvider();

        $this->configureMiddleware();

        if ($this->_config->get('app.autorun')) {
            $this->run();
        }
    }

    /**
     * Override run method
     */
    public function run() {
        if($this->isRunning) {
            return;
        }
        $this->isRunning = true;

        $this->add(new \Bono\Middleware\ErrorHandlerMiddleware());

        parent::run();
    }

    /**
     * Configure life cycle
     */
    protected function configure() {

        if (is_readable($configFile = $this->config('config.path') . '/config.php')) {
            $c = include($configFile);
            if (!is_array($c)) {
                $c = (array) $c;
            }
            $this->config($c);
        }
        if (is_readable($configFile = $this->config('config.path') . '/config-' . $this->config('mode') . '.php')) {
            $c = include($configFile);
            if (!is_array($c)) {
                $c = (array) $c;
            }
            $this->config($c);
        }
    }

    /**
     * Configure handler
     * Right now there are 2 handlers: onNotFound and onError
     */
    protected function configureHandler() {
        $that = $this;
        $onNotFound = function () use ($that) {
            $that->view = new \Slim\View();

            $errorTemplate = $that->_config->get('app.templates.path').'/notFound.php';

            if (is_readable($errorTemplate)) {
                $templateToRender = preg_replace('/\.php?/', '', $errorTemplate);
                $that->view->setLayout(NULL);
                $that->render($templateToRender, array(), 404);
            } else {
                $that->response->setStatus(404);
                echo '<html>
                <head>
                    <title>Ugly Not Found!</title>
                    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
                    <style>
                        body { font-family: Arial; font-size: 14px; line-height: 1.5; color: #333 }
                        h1 { border-bottom: 1px solid #88f; font-weight: normal; }
                        label { margin-top: 10px; display: block; font-size: .8em; font-weight: bold; }
                        pre { margin: 0}
                        blockquote { font-size: .8em; font-style: italic; margin: 0; }
                        .row, .stack-trace { border: 1px solid #f88; padding: 5px; border-radius: 5px; background-color: #fee; overflow: auto; }
                    </style>
                </head>
                <body>
                    <h1>Ugly Not Found!</h1>

                    <p>Whoops! Apparently this is not the page you are looking for.</p>
                    <blockquote>Edit this page by creating templates/notFound.php</blockquote>
                </body>
                </html>';
            }

        };
        $onError = function (\Exception $e) use ($that, $onNotFound) {

            $errorCode = 500;
            if ($e instanceof \Bono\Exception\RestException) {
                $errorCode = $e->getCode();
            }

            if ($errorCode == 404) {
                $onNotFound();
                return;
            }

            $that->view = new \Slim\View();

            $errorData = array(
                'stackTrace' => $e->getTraceAsString(),
                'code' => $e->getCode(),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            );

            $errorTemplate = $that->_config->get('app.templates.path').'/error.php';

            if (is_readable($errorTemplate)) {
                $templateToRender = preg_replace('/\.php?/', '', $errorTemplate);
                $that->render($templateToRender, $errorData, $errorCode);
            } else {
                $that->response->setStatus($errorCode);
                echo '<html>
                <head>
                    <title>Ugly Error!</title>
                    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
                    <style>
                        body { font-family: Arial; font-size: 14px; line-height: 1.5; color: #333 }
                        h1 { border-bottom: 1px solid #f88; font-weight: normal; }
                        label { margin-top: 10px; display: block; font-size: .8em; font-weight: bold; }
                        pre { margin: 0}
                        blockquote { font-size: .8em; font-style: italic; margin: 0; }
                        .row, .stack-trace { border: 1px solid #f88; padding: 5px; border-radius: 5px; background-color: #fee; overflow: auto; }
                    </style>
                </head>
                <body>
                    <h1>Ugly Error!</h1>

                    <p>Something wrong happened.</p>
                    <blockquote>Edit this page by creating templates/error.php</blockquote>

                    <label>Code</label>
                    <div class="row">'.
                        '<code>'. $errorData['code'] .'</code>'.
                    '</div>

                    <label>Message</label>
                    <div class="row"><code>'.$errorData['message'].'</code></div>

                    <label>File</label>
                    <div class="row"><code>'. $errorData['file'] .'</code></div>
                    <label>Line</label>
                    <div class="row"><code>'. $errorData['line'] .'</code></div>

                    <label>Stack Trace</label>
                    <div class="stack-trace">
                        <pre>'. $errorData['stackTrace'] .'</pre>
                    </div>

                </body>
                </html>';
            }
        };

        $this->error($onError);
        $this->notFound($onNotFound);
    }

    /**
     * Configure providers
     */
    protected function configureProvider() {
        $this->providerRepository = new ProviderRepository($this);
        $providers = $this->_config->get('bono.providers') ?: array();
        foreach($providers as $Provider) {
            $this->providerRepository->add(new $Provider());
        }

        $this->providerRepository->initialize();
    }

    /**
     * Configure middlewares
     */
    protected function configureMiddleware() {
        $middlewares = $this->_config->get('bono.middlewares') ?: array();
        foreach ($middlewares as $Middleware) {
            $this->add(new $Middleware());
        }
    }

}
