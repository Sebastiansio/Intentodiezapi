<?php

namespace App\Listeners\GestionMultas;

use SM\Event\TransitionEvent;

class StateHistoryManager
{
    public function handle(TransitionEvent $event): void
    {
        $sm = $event->getStateMachine();
        $model = $sm->getObject();

        $model->history()->create([
            'transition' => $event->getTransition(),
            'to' => $sm->getState(),
            'user_id' => auth()->id(),
            'contexto' => json_encode($model->contexto),
        ]);

        $model->save();
    }
}
