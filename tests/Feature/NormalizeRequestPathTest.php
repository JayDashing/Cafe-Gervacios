<?php

namespace Tests\Feature;

use Tests\TestCase;

class NormalizeRequestPathTest extends TestCase
{
    public function test_duplicate_slashes_redirect_to_the_normalized_path(): void
    {
        $this->get('/admin//tables?from=double-slash')
            ->assertRedirect(url('/admin/tables').'?from=double-slash');
    }
}
