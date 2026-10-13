<?php
declare(strict_types=1);

namespace App\Service;

use Cake\Core\Configure;
use Cake\ORM\TableRegistry;
use Psr\Http\Message\UploadedFileInterface;

class AttachmentService
{
    public const MAX_SIZE = 15 * 1024 * 1024;
    private array $createdFiles = [];

    public function validate(mixed $files): array
    {
        if ($files === null) { return []; }
        if (!is_array($files)) { throw new UploadException('Selecione fotos válidas para anexar.'); }
        $files = array_values(array_filter($files, fn($file) => !$file instanceof UploadedFileInterface || $file->getError() !== UPLOAD_ERR_NO_FILE));
        if (count($files) > 5) {
            throw new UploadException('Selecione no máximo 5 fotos.');
        }
        $accepted = [];
        foreach ($files as $file) {
            if (!$file instanceof UploadedFileInterface) { throw new UploadException('Selecione fotos válidas para anexar.'); }
            if ($file->getError() === UPLOAD_ERR_NO_FILE) { continue; }
            if ($file->getError() !== UPLOAD_ERR_OK) { throw new UploadException('Não foi possível receber uma foto. Confira o tamanho e selecione novamente.'); }
            $size = $file->getSize();
            if (!$size || $size > self::MAX_SIZE) { throw new UploadException('Cada foto deve ter até 15 MB.'); }
            $extension = strtolower(pathinfo((string)$file->getClientFilename(), PATHINFO_EXTENSION));
            $types = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
            if (!isset($types[$extension])) { throw new UploadException('Use fotos JPG, PNG ou WebP. Outros tipos de arquivo não são permitidos.'); }
            $stream = $file->getStream();
            $stream->rewind();
            $content = $stream->read(self::MAX_SIZE + 1);
            $stream->rewind();
            $image = @getimagesizefromstring($content);
            $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($content);
            if (strlen($content) !== $size || !$image || $mime !== $types[$extension] || ($image['mime'] ?? '') !== $mime || $image[0] * $image[1] > 40000000) {
                throw new UploadException('O arquivo não é uma foto válida ou é grande demais. Use JPG, PNG ou WebP de até 15 MB.');
            }
            $name = preg_replace('/[\x00-\x1F\x7F]/u', '', basename(str_replace('\\', '/', (string)$file->getClientFilename())));
            $accepted[] = ['file' => $file, 'mime' => $mime, 'extension' => $extension === 'jpeg' ? 'jpg' : $extension, 'size' => $size, 'name' => mb_substr($name ?: 'foto.' . $extension, 0, 200)];
        }

        return $accepted;
    }

    public function save(array $files, int $requestId, int $userId): void
    {
        if (!$files) { return; }
        $root = self::root();
        if (!is_dir($root) && !mkdir($root, 0700, true) && !is_dir($root)) {
            throw new UploadException('Não foi possível armazenar as fotos. Tente novamente.');
        }
        $tables = TableRegistry::getTableLocator();
        foreach ($files as $file) {
            $stored = bin2hex(random_bytes(24)) . '.' . $file['extension'];
            $path = $root . DIRECTORY_SEPARATOR . $stored;
            $this->createdFiles[] = $path;
            try { $file['file']->moveTo($path); }
            catch (\Throwable $e) { throw new UploadException('Não foi possível armazenar uma foto. Selecione novamente e tente enviar.'); }
            $attachments = $tables->get('RequestAttachments');
            $attachment = $attachments->newEntity([
                'request_id' => $requestId, 'user_id' => $userId, 'original_name' => $file['name'],
                'stored_name' => $stored, 'mime_type' => $file['mime'], 'size' => $file['size'],
                'created_at' => new \Cake\I18n\DateTime('now', 'UTC'),
            ]);
            $attachments->saveOrFail($attachment);
            $audits = $tables->get('RequestAudits');
            $audits->saveOrFail($audits->newEntity([
                'request_id' => $requestId, 'user_id' => $userId, 'action' => 'ATTACHMENT_ADDED',
                'new_values' => ['attachment_id' => $attachment->id, 'name' => $file['name']],
                'created_at' => new \Cake\I18n\DateTime('now', 'UTC'),
            ]));
        }
    }

    public function cleanup(): void
    {
        foreach ($this->createdFiles as $path) { if (is_file($path)) { unlink($path); } }
        $this->createdFiles = [];
    }

    public static function root(): string
    {
        return (string)Configure::read('Attachments.path', ROOT . DS . 'storage' . DS . 'attachments');
    }
}
