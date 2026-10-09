<?php
declare(strict_types=1);

namespace App\Controller;

use App\Domain\Enum\UserRole;
use Cake\Event\EventInterface;
use Cake\Http\Exception\ForbiddenException;

abstract class AdminController extends AppController
{
    public function beforeFilter(EventInterface $event): void
    {
        parent::beforeFilter($event);
        if ($event->isStopped()) { return; }
        $identity = $this->request->getAttribute('identity');
        if ($identity && $identity->get('role') !== UserRole::ADMIN_PCM->value) {
            throw new ForbiddenException('Somente o PCM pode administrar este cadastro.');
        }
    }
}
