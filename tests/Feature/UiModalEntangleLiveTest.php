<?php

test('modal and slide panel use live entangle bindings', function () {
    $modal = file_get_contents(resource_path('views/components/ui/modal.blade.php'));
    $slidePanel = file_get_contents(resource_path('views/components/ui/slide-panel.blade.php'));

    expect($modal)->toContain('@entangle($wireModel).live');
    expect($slidePanel)->toContain('@entangle($wireModel).live');
});
