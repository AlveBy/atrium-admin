<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Views;

use InvalidArgumentException;

/**
 * Cards in columns: `KanbanView::make('status')` sorts the records into the
 * options of the field `status` (a SelectField or a BelongsToField). A card
 * dragged to another column saves that value, the way the form does.
 *
 *     KanbanView::make('status')->title('author_name')->cards('rating', 'posted_at')->order('sort')
 */
final class KanbanView extends View
{
    /** Fields shown on a card besides its title. */
    public const MAX_CARD_FIELDS = 3;

    /** Cards a column gets at first and with every "Show more". */
    public const PER_COLUMN = 20;

    /** @var array<int,string> */
    protected array $cards = [];

    protected ?string $order = null;

    protected int $perColumn = self::PER_COLUMN;

    public function type(): string
    {
        return 'kanban';
    }

    /**
     * Up to three fields of the form to show on a card under the title.
     */
    public function cards(string ...$fields): static
    {
        if (count($fields) > self::MAX_CARD_FIELDS) {
            throw new InvalidArgumentException('A card shows at most '.self::MAX_CARD_FIELDS.' fields, '.count($fields).' given.');
        }

        $this->cards = array_values($fields);

        return $this;
    }

    /**
     * The number field that keeps the order of the cards in a column
     * (`sort`). Without it a column is ordered as the module's list is and a
     * card dropped inside its column stays where it was.
     */
    public function order(string $field): static
    {
        $this->order = $field;

        return $this;
    }

    /**
     * How many cards a column loads at a time.
     */
    public function perColumn(int $cards): static
    {
        $this->perColumn = max(1, $cards);

        return $this;
    }

    /**
     * @return array<int,string>
     */
    public function getCards(): array
    {
        return $this->cards;
    }

    public function getOrder(): ?string
    {
        return $this->order;
    }

    public function getPerColumn(): int
    {
        return $this->perColumn;
    }

    public function writes(): array
    {
        return array_values(array_filter([$this->field, $this->order]));
    }

    public function needs(): array
    {
        return [
            $this->field => ['select', 'belongsTo'],
            ...($this->order === null ? [] : [$this->order => ['number']]),
        ];
    }
}
