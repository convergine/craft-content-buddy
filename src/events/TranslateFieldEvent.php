<?php

namespace convergine\contentbuddy\events;

use craft\base\FieldInterface;
use craft\events\CancelableEvent;

class TranslateFieldEvent extends CancelableEvent
{
    public FieldInterface $field;
}