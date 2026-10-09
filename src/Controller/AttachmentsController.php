<?php
declare(strict_types=1);

namespace App\Controller;

use App\Domain\Enum\UserRole;
use App\Service\AttachmentService;
use Cake\Http\Exception\NotFoundException;

class AttachmentsController extends AppController
{
    public function view(string $id): mixed
    {
        $this->request->allowMethod(['get']);
        $identity = $this->request->getAttribute('identity');
        $query = $this->fetchTable('RequestAttachments')->find()->contain(['ServiceRequests'])->where(['RequestAttachments.id' => $id]);
        $query->where(\App\Service\RequestAccess::conditions($identity->get('role'), (int)$identity->getIdentifier()));
        $attachment = $query->first();
        if (!$attachment || !preg_match('/^[a-f0-9]{48}\.(jpg|png|webp)$/', $attachment->stored_name)) { throw new NotFoundException('Foto não encontrada.'); }
        $path = AttachmentService::root() . DS . $attachment->stored_name;
        if (!is_file($path)) { throw new NotFoundException('Foto indisponível. Entre em contato com o PCM.'); }

        return $this->response->withFile($path, ['download' => false])
            ->withType($attachment->mime_type)->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('Cache-Control', 'private, no-store');
    }
}
