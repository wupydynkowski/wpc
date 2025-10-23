<?php
/**
 * @package AWS Price Calculator
 * @author Enrico Venezia
 * @copyright (C) Altos Web Solutions Italia
 * @license GNU/GPL v2 http://www.gnu.org/licenses/gpl-2.0.html
**/

namespace WSF\Controller;

/*AWS_PHP_HEADER*/

use WSF\Helper\FrameworkHelper;
use WSF\Helper\PluginHelper;

class IndexController {
    private $wsf;
    
    public function __construct(FrameworkHelper $wsf){
        $this->wsf = $wsf;
        
        $this->pluginHelper = $this->wsf->get('\\WSF\\Helper', true, 'awspricecalculator/Helper', 'PluginHelper', array($wsf));
    }
    
    public function indexAction(){
        $logo       = $this->pluginHelper->logo();
        $icon       = $this->pluginHelper->icon();
        $credits    = $this->pluginHelper->getCreditsUrl();

        $this->wsf->renderView('index/index.php', array(
            'logo'                  => $logo,
            'icon'                  => $icon,
            'homeUrl'               => $this->pluginHelper->getHomeUrl(),
            'documentationUrl'      => $this->pluginHelper->getDocumentationUrl(),
            'forumUrl'              => $this->pluginHelper->getForumUrl(),
            'credits'               => $credits,
            //'controller'           => $this->wsf->getCurrentControllerName(),
            'controller'            => $this->wsf->requestValue("controller"),
            'extensions'            => $this->wsf->checkRequiredExtensions(),
            'directories'           => $this->wsf->checkRequiredDirectories(),
        ));
        
        $firstExecution = $this->wsf->getFirstExecution();

        if($firstExecution['controller'] == 'IndexController' &&
           $firstExecution['action']     == 'indexAction'){

            $this->wsf->execute('awspricecalculator', true, 'field', 'index');
        }
    }
    
    public function footerAction(){
        $this->wsf->renderView('app/footer.php');
    }

}