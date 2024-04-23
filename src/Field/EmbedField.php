<?php

namespace Madforit\EasyAdminEmbedBundle\Field;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use EasyCorp\Bundle\EasyAdminBundle\Field\FieldTrait;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;

/**
 * @author Lukas Lücke <lukas@luecke.me>
 */
final class EmbedField implements FieldInterface
{
    use FieldTrait;



    public static function new(string $propertyName, ?string $label = null): self
    {
        return (new self())
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setTemplateName('crud/field/embed');
    }

    public function setCrudControllerFcqn(string $crudControllerFcqn): self
    {
        $this->dto->setCustomOption('crudControllerFcqn', $crudControllerFcqn);
        return $this;
    }

    public function getCrudControllerFcqn(): ?string
    {
        return $this->dto->getCustomOption('crudControllerFcqn');
    }

}
