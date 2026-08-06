<?php

declare(strict_types=1);

namespace App\Modules\Core\Controllers;

use App\Controllers\BaseController;
use App\Modules\Core\Config\HelpCenter;
use CodeIgniter\Exceptions\PageNotFoundException;

/**
 * Help Center — in-app documentation for end users.
 *
 * Content and access are driven entirely by the HelpCenter registry, so this
 * controller stays generic: it never references a specific module. New guides
 * appear here automatically once registered.
 */
class Help extends BaseController
{
    /** Landing page: cards for every guide the user can access. */
    public function index(): string
    {
        return view('App\Modules\Core\Views\help\index', [
            'pageTitle' => 'Centro de ayuda',
            'topics'    => HelpCenter::accessibleTopics(),
        ]);
    }

    /** A single guide, rendered with a table of contents and its content view. */
    public function show(string $key): string
    {
        $topic = HelpCenter::find($key);

        // Unknown slug, or a guide the user is not allowed to open, both read as
        // "not found" so we never leak the existence of a gated module's guide.
        if ($topic === null || ! HelpCenter::canAccess($topic)) {
            throw PageNotFoundException::forPageNotFound();
        }

        return view('App\Modules\Core\Views\help\show', [
            'pageTitle' => $topic['title'] . ' · Ayuda',
            'topic'     => $topic,
        ]);
    }
}
