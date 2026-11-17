<?php
declare(strict_types=1);
namespace App\Controller;

use App\Service\{CentralAccessService, OidcRedirect, PcmOidcClient, SsoAudit, SsoSettings};
use Cake\Core\Configure;

class SsoController extends AppController
{
    public function initialize(): void
    {
        parent::initialize();
        $this->Authentication->allowUnauthenticated(['login', 'callback']);
    }

    public function login(): mixed
    {
        $this->request->allowMethod(['get']);
        if (!Configure::read('Sso.enabled')) { throw new \Cake\Http\Exception\NotFoundException(); }
        $session = $this->request->getSession();
        if ($session->read('Sso') && $this->request->getAttribute('identity')) {
            return $this->redirect(['controller' => 'Home', 'action' => 'index']);
        }
        try {
            $settings = SsoSettings::read();
            $this->Authentication->logout();
            $session->delete('Sso');
            $session->delete('OidcTransaction');
            $session->renew();
            $session->write('OidcTransaction.created', time());
            // A biblioteca usa $_REQUEST: aceite exclusivamente os parâmetros gerados neste fluxo.
            $original = $_REQUEST;
            $_REQUEST = [];
            try { (new PcmOidcClient($session, $settings))->authenticate(); }
            finally { $_REQUEST = $original; }
        } catch (OidcRedirect $redirect) {
            return $this->redirect($redirect->url)->withHeader('Cache-Control', 'no-store')->withHeader('Referrer-Policy', 'no-referrer');
        } catch (\Throwable $e) {
            $session->delete('OidcTransaction');
        }
        return $this->denied();
    }

    public function callback(): mixed
    {
        $this->request->allowMethod(['get']);
        if (!Configure::read('Sso.enabled')) { throw new \Cake\Http\Exception\NotFoundException(); }
        $session = $this->request->getSession();
        try {
            $settings = SsoSettings::read();
            $query = $this->request->getQueryParams();
            $created = $session->read('OidcTransaction.created');
            $state = $session->read('OidcTransaction.openid_connect_state');
            if (!is_int($created) || time() - $created > 300 || $created > time() ||
                !is_string($state) || !is_string($query['state'] ?? null) || !hash_equals($state, $query['state']) ||
                !is_string($session->read('OidcTransaction.openid_connect_code_verifier')) ||
                !is_string($query['code'] ?? null) || $query['code'] === '' || isset($query['error'])) {
                throw new \RuntimeException('Transação inválida.');
            }
            $client = new PcmOidcClient($session, $settings);
            $original = $_REQUEST;
            $_REQUEST = ['state' => $query['state'], 'code' => $query['code']];
            try { if (!$client->authenticate()) { throw new \RuntimeException(); } }
            finally { $_REQUEST = $original; $session->delete('OidcTransaction'); }
            $claims = $client->getVerifiedClaims();
            $link = $this->fetchTable('SsoIdentities')->find()->contain(['Users'])
                ->where(['issuer' => $claims->iss, 'subject' => $claims->sub])->first();
            if (!$link || !$link->user->active || !(new CentralAccessService())->allowed($claims->iss, $claims->sub)) {
                SsoAudit::record('SSO_DENIED', $link ? (int)$link->user_id : null);
                return $this->denied();
            }
            SsoAudit::record('SSO_LOGIN', (int)$link->user_id);
            $session->renew();
            $this->Authentication->setIdentity($link->user);
            $session->write('Sso', ['issuer' => $claims->iss, 'subject' => $claims->sub, 'exp' => $claims->exp,
                'userId' => (int)$link->user_id]);
            return $this->redirect(['controller' => 'Home', 'action' => 'index'])->withHeader('Cache-Control', 'no-store')
                ->withHeader('Referrer-Policy', 'no-referrer');
        } catch (\Throwable $e) {
            $session->delete('OidcTransaction');
            SsoAudit::record('SSO_FAILED', null);
            return $this->denied();
        }
    }

    private function denied(): mixed
    {
        $this->Authentication->logout();
        $this->request->getSession()->delete('Sso');
        return $this->response->withStatus(403)->withType('text/plain')->withHeader('Cache-Control', 'no-store')
            ->withHeader('Referrer-Policy', 'no-referrer')->withStringBody('Não foi possível autorizar a entrada. Solicite acesso pela Central de Sistemas PCM e confirme o vínculo da sua conta com o PCM.');
    }
}
