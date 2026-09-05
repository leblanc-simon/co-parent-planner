<?php

declare(strict_types=1);

namespace App\Dto\Exchange;

use App\Entity\ExchangeOption;

/** Option d'une décision exposée : libellé, sous-libellé, position et décompte des validations. */
class OptionView
{
    public string $id = '';
    public string $label = '';
    public ?string $sub = null;
    public int $position = 0;
    public int $voteCount = 0;
    public bool $decided = false;
    /**
     * Cette option est **celle que j'ai validée** (X1). Le décompte seul ne le disait pas : après
     * avoir voté, il passait de 0 à 1 sans jamais indiquer que ce 1, c'était vous.
     */
    public bool $myVote = false;

    public static function fromEntity(ExchangeOption $option, int $voteCount, bool $decided, bool $myVote = false): self
    {
        $view = new self();
        $view->id = (string) $option->getId();
        $view->label = $option->getLabel();
        $view->sub = $option->getSub();
        $view->position = $option->getPosition();
        $view->voteCount = $voteCount;
        $view->decided = $decided;
        $view->myVote = $myVote;

        return $view;
    }
}
