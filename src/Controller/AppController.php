<?php
declare(strict_types=1);

/**
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link      https://cakephp.org CakePHP(tm) Project
 * @since     0.2.9
 * @license   https://opensource.org/licenses/mit-license.php MIT License
 */
namespace App\Controller;

use Cake\Controller\Controller;

/**
 * Application Controller
 *
 * Add your application-wide methods in the class below, your controllers
 * will inherit them.
 *
 * @link https://book.cakephp.org/5/en/controllers.html#the-app-controller
 */
class AppController extends Controller
{
    public function beforeFilter(\Cake\Event\EventInterface $event): void
    {
        parent::beforeFilter($event);
        $identity = $this->request->getAttribute('identity');
        $controller = $this->request->getParam('controller');
        $action = $this->request->getParam('action');
        $sso = $this->request->getSession()->read('Sso');
        if ($sso && !($controller === 'Home' && $action === 'logout')) {
            if (!$identity || !(new \App\Service\SsoSessionGuard())->valid($sso, (int)$identity->getIdentifier())) {
                \App\Service\SsoAudit::record('SSO_SESSION_DENIED', $identity ? (int)$identity->getIdentifier() : null);
                $this->Authentication->logout();
                $this->request->getSession()->delete('Sso');
                $event->setResult($this->response->withStatus(403)->withType('text/plain')->withHeader('Cache-Control', 'no-store')
                    ->withStringBody('Acesso não autorizado. Solicite acesso pela Central de Sistemas PCM ou tente novamente quando o serviço estiver disponível.'));
                $event->stopPropagation();
                return;
            }
        }
        $allowed = ($controller === 'Profile' && $action === 'password') || ($controller === 'Home' && in_array($action, ['login', 'logout', 'activity'], true));
        if ($identity && $identity->get('must_change_password') && !$allowed) {
            $event->setResult($this->redirect(['controller' => 'Profile', 'action' => 'password']));
            $event->stopPropagation();
        }
    }
    /**
     * Initialization hook method.
     *
     * Use this method to add common initialization code like loading components.
     *
     * e.g. `$this->loadComponent('FormProtection');`
     *
     * @return void
     */
    public function initialize(): void
    {
        parent::initialize();

        $this->loadComponent('Flash');
        $this->loadComponent('Authentication.Authentication');

        /*
         * Enable the following component for recommended CakePHP form protection settings.
         * see https://book.cakephp.org/5/en/controllers/components/form-protection.html
         */
        //$this->loadComponent('FormProtection');
    }
}
