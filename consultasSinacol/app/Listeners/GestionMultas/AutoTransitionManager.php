<?php

namespace App\Listeners\GestionMultas;

use SM\Event\TransitionEvent;

class AutoTransitionManager
{
    public function handle(TransitionEvent $event): void
    {
        $sm = $event->getStateMachine();
        $model = $sm->getObject();

        $next = collect($model->getPossibleTransitions())->first();
        if (! $next) {
            return;
        }
        $meta = $sm->metadata()->transition($next);
        if (isset($meta['auto'])) {
            $model->transition($next);
            $model->save();
        }
    }
}
