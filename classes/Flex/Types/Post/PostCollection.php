<?php
declare(strict_types=1);

namespace Grav\Plugin\MetaFeed\Flex\Types\Post;

use Grav\Common\Flex\Types\Generic\GenericCollection;

class PostCollection extends GenericCollection
{
    public function getActive(): self
    {
        return $this->filterBy(['active' => true]);
    }

    public function byProvider(string $provider): self
    {
        return $this->filterBy(['provider' => $provider]);
    }
}
