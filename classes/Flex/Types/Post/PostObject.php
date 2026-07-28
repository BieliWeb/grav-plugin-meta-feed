<?php
declare(strict_types=1);

namespace Grav\Plugin\MetaFeed\Flex\Types\Post;

use Grav\Common\Flex\Types\Generic\GenericObject;

class PostObject extends GenericObject
{
    public function isActive(): bool
    {
        return (bool)$this->getProperty('active', true);
    }

    public function getProvider(): string
    {
        return (string)$this->getProperty('provider', '');
    }

    public function getMessage(): string
    {
        return (string)$this->getProperty('message', '');
    }

    public function getImageUrl(): string
    {
        return (string)$this->getProperty('image_url', '');
    }

    public function getThumbnailUrl(): string
    {
        return (string)$this->getProperty('thumbnail_url', '');
    }

    public function getMediaType(): string
    {
        return (string)$this->getProperty('media_type', 'IMAGE');
    }

    public function getLink(): string
    {
        return (string)$this->getProperty('link', '');
    }

    public function getPublishedAt(): string
    {
        return (string)$this->getProperty('published_at', '');
    }
}
