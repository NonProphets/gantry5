<?php

/**
 * @package   Gantry5
 * @author    RocketTheme http://www.rockettheme.com
 * @copyright Copyright (C) 2007 - 2015 RocketTheme, LLC
 * @license   Dual License: MIT or GNU/GPLv2 and later
 *
 * http://opensource.org/licenses/MIT
 * http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Gantry Framework code that extends GPL code is considered GNU/GPLv2 and later
 */

namespace Gantry\Admin\Controller\Html\Configurations;

use Gantry\Component\Config\BlueprintsForm;
use Gantry\Component\Config\Config;
use Gantry\Component\Controller\HtmlController;
use Gantry\Component\Filesystem\Folder;
use Gantry\Component\Request\Request;
use Gantry\Component\Response\JsonResponse;
use Gantry\Framework\Base\Gantry;
use RocketTheme\Toolbox\Blueprints\Blueprints;
use RocketTheme\Toolbox\Event\Event;
use RocketTheme\Toolbox\File\YamlFile;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;

class Content extends HtmlController
{
    protected $httpVerbs = [
        'GET' => [
            '/'               => 'index',
            '/content'        => 'undefined',
            '/content/*'      => 'display',
            '/content/*/**'   => 'formfield',
        ],
        'POST' => [
            '/'               => 'save',
            '/content'        => 'forbidden',
            '/content/*'      => 'save',
            '/content/*/**'   => 'formfield'
        ],
        'PUT' => [
            '/'            => 'save',
            '/content'     => 'forbidden',
            '/content/*'   => 'save'
        ],
        'PATCH' => [
            '/'            => 'save',
            '/content'     => 'forbidden',
            '/content/*'   => 'save'
        ],
        'DELETE' => [
            '/'            => 'forbidden',
            '/content'     => 'forbidden',
            '/content/*'   => 'reset'
        ]
    ];

    public function index()
    {
        $configuration = $this->params['configuration'];

        if($configuration == 'default') {
            $this->params['overrideable'] = false;
        } else {
            $this->params['defaults'] = $this->container['defaults'];
            $this->params['overrideable'] = true;
        }

        $this->params['content'] = $this->container['content']->group();
        $this->params['route']  = "configurations.{$this->params['configuration']}.content";
        $this->params['page_id'] = $configuration;

        return $this->container['admin.theme']->render('@gantry-admin/pages/configurations/content/content.html.twig', $this->params);
    }

    public function display($id)
    {
        $configuration = $this->params['configuration'];
        $particle = $this->container['content']->get($id);
        $blueprints = new BlueprintsForm($particle);
        $prefix = 'content.' . $id;

        if($configuration == 'default') {
            $this->params['overrideable'] = false;
        } else {
            $this->params['defaults'] = $this->container['defaults']->get($prefix);
            $this->params['overrideable'] = true;
        }

        $this->params += [
            'particle' => $blueprints,
            'data' =>  Gantry::instance()['config']->get($prefix),
            'id' => $id,
            'parent' => "configurations/{$this->params['configuration']}/content",
            'route'  => "configurations.{$this->params['configuration']}.content.{$prefix}",
            'skip' => ['enabled']
            ];

        return $this->container['admin.theme']->render('@gantry-admin/pages/configurations/content/item.html.twig', $this->params);
    }

    public function formfield($id)
    {
        $path = func_get_args();

        if (end($path) == 'validate') {
            return call_user_func_array([$this, 'validate'], $path);
        }

        $particle = $this->container['content']->get($id);

        // Load blueprints.
        $blueprints = new BlueprintsForm($particle);

        list($fields, $path, $value) = $blueprints->resolve(array_slice($path, 1), '/');

        if (!$fields) {
            throw new \RuntimeException('Page Not Found', 404);
        }

        $data = $this->request->post->getJsonArray('data');

        $offset = "content.{$id}." . implode('.', $path);
        if ($value !== null) {
            $parent = $fields;
            $fields = ['fields' => $fields['fields']];
            $offset .= '.' . $value;
            $data = $data ?: $this->container['config']->get($offset);
            $data = ['data' => $data];
            $prefix = 'data.';
        } else {
            $data = $data ?: $this->container['config']->get($offset);
            $prefix = 'data';
        }

        $fields['is_current'] = true;

        array_pop($path);

        $configuration = "configurations/{$this->params['configuration']}";
        $this->params = [
                'configuration' => $configuration,
                'blueprints' => $fields,
                'data' => $data,
                'prefix' => $prefix,
                'parent' => $path
                    ? "$configuration/content/content/{$id}/" . implode('/', $path)
                    : "$configuration/content/content/{$id}",
                'route' => 'content.' . $offset
            ] + $this->params;

        if (isset($parent['key'])) {
            $this->params['key'] = $parent['key'];
        }
        if (isset($parent['value'])) {
            $this->params['title'] = $parent['value'];
        }

        return $this->container['admin.theme']->render('@gantry-admin/pages/configurations/content/field.html.twig', $this->params);
    }

    public function validate($particle)
    {
        $path = implode('.', array_slice(func_get_args(), 1, -1));

        // Validate only exists for JSON.
        if (empty($this->params['ajax'])) {
            $this->undefined();
        }

        // Load particle blueprints.
        $validator = $this->container['content']->get($particle);

        // Create configuration from the defaults.
        $data = new Config(
            [],
            function () use ($validator) {
                return $validator;
            }
        );

        $data->join($path, $this->request->post->getArray('data'));

        // TODO: validate

        return new JsonResponse(['data' => $data->get($path)]);
    }

    public function save($id = null)
    {
        $data = $id ? [$id => $this->request->post->getArray()] : $this->request->post->getArray('content');

        foreach ($data as $name => $values) {
            $this->saveItem($name, $values);
        }

        // Fire save event.
        $event = new Event;
        $event->gantry = $this->container;
        $event->theme = $this->container['theme'];
        $event->controller = $this;
        $event->data = $data;
        $this->container->fireEvent('admin.content.save', $event);

        return $id ? $this->display($id) : $this->index();
    }

    protected function saveItem($id, $data)
    {
        /** @var UniformResourceLocator $locator */
        $locator = $this->container['locator'];

        // Save layout into custom directory for the current theme.
        $configuration = $this->params['configuration'];
        $save_dir = $locator->findResource("gantry-config://{$configuration}/content", true, true);
        $filename = "{$save_dir}/{$id}.yaml";

        $file = YamlFile::instance($filename);
        if (!is_array($data)) {
            if ($file->exists()) {
                $file->delete();
            }
        } else {
            $blueprints = new BlueprintsForm($this->container['content']->get($id));
            $config = new Config($data, function() use ($blueprints) { return $blueprints; });

            $file->save($config->toArray());
        }
    }

    public function reset($id)
    {
        $this->params += [
            'data' => [],
        ];

        return $this->display($id);
    }
}
