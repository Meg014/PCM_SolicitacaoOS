<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\AssetsLookupInterface;
use App\Service\AssetsUnavailableException;

class LookupsController extends AppController
{
    public function assets(AssetsLookupInterface $assets): mixed
    {
        $this->request->allowMethod(['get']);
        $term = $this->request->getQuery('q', '');
        if (!is_string($term)) {
            return $this->json(['error' => 'Informe um texto para pesquisar.'], 400);
        }
        try {
            return $this->json(['results' => $assets->search($term)]);
        } catch (AssetsUnavailableException $e) {
            return $this->json(['error' => $e->getMessage()], 503);
        }
    }

    public function sectors(): mixed
    {
        $this->request->allowMethod(['get']);
        $term = $this->request->getQuery('q', '');
        if (!is_string($term)) {
            return $this->json(['error' => 'Informe um texto para pesquisar.'], 400);
        }
        $term = trim($term);
        if (mb_strlen($term) < 2 || mb_strlen($term) > 60) {
            return $this->json(['results' => []]);
        }
        $term = \App\Service\SearchText::normalize($term);
        $pattern = '%' . strtr($term, ['~' => '~~', '%' => '~%', '_' => '~_']) . '%';
        try {
            $rows = $this->fetchTable('Sectors')->getConnection()->execute(
                "SELECT id, name FROM sectors WHERE active = true AND (" . \App\Service\SearchText::sql('name') . " LIKE :name ESCAPE '~' OR " . \App\Service\SearchText::sql('code') . " LIKE :code ESCAPE '~') ORDER BY name LIMIT 20",
                ['name' => $pattern, 'code' => $pattern],
            )->fetchAll('assoc');
        } catch (\PDOException $e) {
            return $this->json(['error' => 'Não foi possível consultar as mãos de obra agora. Tente novamente.'], 503);
        }

        return $this->json(['results' => $rows]);
    }

    private function json(array $data, int $status = 200): mixed
    {
        return $this->response->withStatus($status)->withType('application/json')
            ->withHeader('Cache-Control', 'no-store')
            ->withStringBody(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    public function active(): mixed
    {
        $this->request->allowMethod(['get']);
        $code = $this->request->getQuery('code');
        $branch = $this->request->getQuery('branch');
        if (!is_string($code) || !is_string($branch) || $code === '' || strlen($code) > 100 || strlen($branch) > 100) {
            return $this->json(['error' => 'Selecione um equipamento válido.'], 400);
        }
        $identity = $this->request->getAttribute('identity');
        $table = $this->fetchTable('ServiceRequests');
        $rows = [];
        foreach (\App\Service\ActiveAssetRequests::find($code, $branch) as $request) {
            $visible = $table->find()->where(['ServiceRequests.id' => $request->id])
                ->where(\App\Service\RequestAccess::conditions($identity->get('role'), (int)$identity->getIdentifier()))->count() > 0;
            $rows[] = ['number' => $request->number, 'os' => $request->totvs_os_number, 'labor' => $request->sector->name,
                'created' => $request->created_at->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i'),
                'requester' => $request->user->name, 'status' => \App\Domain\Enum\RequestStatus::from($request->status)->label(),
                'description' => $request->description, 'url' => $visible ? \Cake\Routing\Router::url(['controller' => 'Requests', 'action' => 'view', $request->id]) : null];
        }
        return $this->json(['results' => $rows]);
    }
}
