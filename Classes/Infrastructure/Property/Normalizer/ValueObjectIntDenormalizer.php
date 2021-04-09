<?php
declare(strict_types=1);

namespace Neos\ContentRepository\Intermediary\Domain\Property\Normalizer;

use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

final class ValueObjectIntDenormalizer implements DenormalizerInterface
{
    public function denormalize($data, $type, string $format = null, array $context = [])
    {
        return $type::fromInt($data);
    }

    public function supportsDenormalization($data, $type, string $format = null)
    {
        return is_int($data) && class_exists($type) && method_exists($type, 'fromInt');
    }
}
