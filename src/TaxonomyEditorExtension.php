<?php

namespace Bolt\Extension\Soapbox\TaxonomyEditor;

use Silex\Application;
use Bolt\Menu\MenuEntry;
use Bolt\Controller\Zone;
use Bolt\Asset\File\JavaScript;
use Bolt\Asset\File\Stylesheet;
use Bolt\Extension\SimpleExtension;
use Bolt\Version as Version;
use Silex\ControllerCollection;
use Bolt\Translation\Translator as Trans;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Yaml\Dumper;
use Symfony\Component\Yaml\Parser;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * TaxonomyEditor extension class.
 *
 * @package TaxonomyEditor
 * @author  Graham May <graham.may@soapbox.co.uk>
 */
class TaxonomyEditorExtension extends SimpleExtension
{

    /**
     * @var \Bolt\Filesystem\Handler\YamlFile|null
     */
    private $taxonomy_file = null;

    /**
     * Pretty extension name
     *
     * @return string
     */
    public function getDisplayName()
    {

        return 'Taxonomy Editor Extension';
    }

    /**
     * Add routes for this extension to Bolt's Backend
     * {@inheritdoc}
     */
    protected function registerBackendRoutes(ControllerCollection $collection)
    {

        //Since version 3.3 ther is a new mounting point for the extensions
        if (Version::compare('3.3', '>')) {
            $collection->match('/extend/taxonomyeditor', [
                $this,
                'taxonomyEditor'
            ]);
        } else {
            $collection->match('/extensions/taxonomyeditor', [
                $this,
                'taxonomyEditor'
            ]);
        }
    }

    /**
     * Add Menu item for this extension to Bolt's Backend
     * {@inheritdoc}
     */
    protected function registerMenuEntries()
    {

        $config = $this->getConfig();

        $menu = new MenuEntry('taxonomyeditor', 'taxonomyeditor');
        $menu->setLabel(Trans::__('taxonomyeditor.taxonomyitem', ['DEFAULT' => 'Taxonomy editor']))
             ->setIcon('fa:tags')
             ->setPermission($config['permission']);

        return [
            $menu,
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function registerTwigPaths()
    {

        return [
            'templates' => [
                'position'  => 'prepend',
                'namespace' => 'bolt'
            ]
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function getDefaultConfig()
    {

        return [
            'fields'     => [],
            'backups'    => [
                'enable' => false
            ],
            'permission' => 'files:config'
        ];
    }

    /**
     * TaxonomyEditor route
     *
     * @param  Application $app
     * @param  Request     $request
     *
     * @return Response|RedirectResponse
     */
    public function taxonomyEditor(Application $app, Request $request)
    {

        $config = $this->getConfig();

        $this->registerEditorAssets($app);

        // Block unauthorized access...
        if (!$app['users']->isAllowed($config['permission'])) {
            throw new AccessDeniedException(Trans::__('taxonomyeditor.notallowed', ['DEFAULT' => 'The currently logged in user does not have the correct rights to use this route.']));
        }

        // Handle posted taxonomies
        if ($request->get('taxonomies')) {
            try {
                $taxonomies = json_decode($request->get('taxonomies'), true);

                // Throw JSON error if we couldn't decode it
                if (json_last_error() !== 0) {
                    throw new \Exception('JSON Error');
                }

                // Modify the taxonomy config to contain the submitted terms
                $updated_taxonomy_config_array = $this->addTermsToConfig($taxonomies);

                // Prepare array as string
                $dumper = new Dumper();
                $dumper->setIndentation(4);
                $updated_taxonomy_config = $dumper->dump($updated_taxonomy_config_array, 9999);

                // Prepare string as yaml
                $parser = new Parser();
                $parser->parse($updated_taxonomy_config);
            } catch (\Exception $e) {
                // Don't save taxonomy file if we got a json on yaml error
                $app['logger.flash']->error(Trans::__('taxonomyeditor.flash.error', ['DEFAULT' => 'Taxonomy couldn\'t be saved, we have restored it to it\'s last known good state.']));

                return new RedirectResponse($app['resources']->getUrl('currenturl'), 301);
            }

            // Handle backups
            if ($config['backups']['enable']) {
                $app['filesystem']->createDir($config['backups']['folder']);

                // Create new backup
                $backup = $dumper->dump($app['config']->get('taxonomy'), 9999);

                $app['filesystem']->put($config['backups']['folder'] . '/taxonomies.' . time() . '.yml', $backup);

                // Delete oldest backup if we have too many
                $backups = $app['filesystem']->listContents($config['backups']['folder']);

                if (count($backups) > $config['backups']['keep']) {
                    reset($backups)->delete();
                }
            }

            // Save taxonomy file
            $taxonomy_config = $this->getTaxonomyFile()
                                    ->put($updated_taxonomy_config);

            $app['logger.flash']->success(Trans::__('taxonomyeditor.flash.saved', ['DEFAULT' => 'The taxonomies have been saved']));

            return new RedirectResponse($app['resources']->getUrl('currenturl'), 301);
        }

        // Handle restoring backups
        if ($request->get('backup')) {
            $backup = $app['filesystem']->get($config['backups']['folder'])
                                        ->get($request->get('backup'));

            $app['filesystem']->put('config://taxonomy.yml', $backup->read());

            $app['logger.flash']->success(Trans::__('taxonomyeditor.flash.backup', [
                'DEFAULT' => 'Backup restored',
                '%time%'  => $backup->getCarbon()
                                    ->diffForHumans()
            ]));

            return new RedirectResponse($app['resources']->getUrl('currenturl'), 301);
        }

        $html = $this->renderTaxonomyPage($app);

        return new Response($html);
    }

    /**
     * renderTaxonomyPage
     *
     * @param  Application $app
     *
     * @return string $html
     */
    private function renderTaxonomyPage($app)
    {

        $config     = $this->getConfig();
        $taxonomies = $this->getEditableTaxonomies($app);

        // Get data and render backend view
        // @formatter:off
        $data = [
            'taxonomies'      => $taxonomies,
            'taxonomy_config' => $config,
            'JsTranslations'  => json_encode([
                'taxonomyeditor.js.loading'               => Trans::__('taxonomyeditor.js.loading', ['DEFAULT' => 'Loading suggestions']),
                'taxonomyeditor.js.newlink'               => Trans::__('taxonomyeditor.js.newlink', ['DEFAULT' => 'New link to']),
                'taxonomyeditor.actions.showhidechildren' => Trans::__('taxonomyeditor.actions.showhidechildren', ['DEFAULT' => 'Click to show/hide children']),
                'taxonomyeditor.action.showhideeditor'    => Trans::__('taxonomyeditor.action.showhideeditor', ['DEFAULT' => 'Click to show/hide item editor']),
                'taxonomyeditor.action.delete'            => Trans::__('taxonomyeditor.action.delete', ['DEFAULT' => 'Click to delete item']),
                'taxonomyeditor.fields.slug'              => Trans::__('taxonomyeditor.fields.slug', ['DEFAULT' => 'Slug']),
                'taxonomyeditor.fields.name'              => Trans::__('taxonomyeditor.fields.name', ['DEFAULT' => 'Name']),
            ])
        ];
        // @formatter:on

        if ($config['backups']['enable']) {
            $data['backups'] = $app['filesystem']->listContents($config['backups']['folder']);
        }

        return $app['twig']->render("@bolt/taxonomyeditor.twig", $data);
    }

    /**
     * getEditableTaxonomies - Exclude behaves_like tags taxonomies
     *
     * @param  Application $app
     *
     * @return array $taxonomies
     */
    private function getEditableTaxonomies($app)
    {

        $all_taxonomies = $app['config']->get('taxonomy');
        $taxonomies     = [];

        // Remove behaves_like tags
        if (!empty($all_taxonomies) && is_array($all_taxonomies)) {
            foreach ($all_taxonomies as $taxonomy) {
                if (!empty($taxonomy['behaves_like']) && $taxonomy['behaves_like'] !== 'tags') {
                    $taxonomies[] = $taxonomy;
                }
            }
        }

        // Sort Taxonomies by name
        usort($taxonomies, function ($a, $b) {

            if ($a['name'] == $b['name']) {
                return 0;
            }

            return ($a['name'] < $b['name']) ? -1 : 1;
        });

        return $taxonomies;
    }

    /**
     * registerEditorAssets - Add the required frontend assets for the editor page
     *
     * @param  Application $app
     */
    private function registerEditorAssets($app)
    {

        $assets = [
            new JavaScript('taxonomyeditor.js'),
            new Stylesheet('taxonomyeditor.css'),
            new JavaScript('jquery.mjs.nestedSortable.js')
        ];

        foreach ($assets as $asset) {
            $asset->setZone(Zone::BACKEND);

            $file = $this->getWebDirectory()
                         ->getFile($asset->getPath());

            $asset->setPackageName('extensions')
                  ->setPath($file->getPath());

            $app['asset.queue.file']->add($asset);
        }
    }

    /**
     * Get the taxonomy config file
     *
     * @return \Bolt\Filesystem\Handler\YamlFile|null
     */
    private function getTaxonomyFile()
    {

        $app = $this->getContainer();

        if (is_null($this->taxonomy_file)) {
            $this->taxonomy_file = $app['filesystem']->getFile('config://taxonomy.yml');
        }

        return $this->taxonomy_file;
    }

    /**
     * Prepare the config array to be converted into YAML
     *
     * @param $taxonomies
     *
     * @return mixed
     */
    private function addTermsToConfig($taxonomies)
    {

        // Get current taxonomy config
        $parser                = new Parser();
        $taxonomy_config       = $this->getTaxonomyFile();
        $taxonomy_config_array = $parser->parse($taxonomy_config->read());

        $updated_taxonomy_config_array = $taxonomy_config_array;

        foreach ($taxonomies as $taxonomy_slug => $taxonomy_terms) {
            if (!empty($updated_taxonomy_config_array[$taxonomy_slug])) {
                $updated_taxonomy_config_array[$taxonomy_slug]['options'] = $this->getTaxonomyTermsArray($taxonomy_terms);
            }
        }

        return $updated_taxonomy_config_array;
    }

    /**
     * Get a flattened array of taxonomy terms from the posted data
     *
     * @param $taxonomy_terms
     *
     * @return array
     */
    private function getTaxonomyTermsArray($taxonomy_terms)
    {

        $sorted_array = [];

        foreach ($taxonomy_terms as $term) {
            $sorted_array[$term['slug']] = $term['name'];
        }

        return $sorted_array;
    }
}
