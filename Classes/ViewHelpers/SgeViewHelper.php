<?php

declare(strict_types=1);

namespace Lochmueller\SealAi\ViewHelpers;

use Lochmueller\SealAi\AiBridge;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

class SgeViewHelper extends AbstractViewHelper
{
    protected $escapeOutput = false;

    public function __construct(private readonly AiBridge $aiBridge) {}

    public function initializeArguments(): void
    {
        $this->registerArgument('items', 'array', 'Paginated search result items', true);
    }

    public function render(): string
    {
        $items = $this->arguments['items'];
        if ($items === []) {
            return '';
        }

        if ($this->renderingContext === null || !$this->renderingContext->hasAttribute(ServerRequestInterface::class)) {
            return '';
        }

        $site = $this->renderingContext->getAttribute(ServerRequestInterface::class)->getAttribute('site');
        if (!$site instanceof Site) {
            return '';
        }

        if (($site->getConfiguration()['sealAiChatModel'] ?? '') === '') {
            return '';
        }

        $this->aiBridge->initialize($site);
        $chat = $this->aiBridge->getChat();
        if ($chat === null) {
            return '';
        }

        try {
            $summary = $chat->complete(
                'You are a helpful search assistant. Summarize the following search results into a concise, '
                . 'informative overview. Highlight the most relevant information. '
                . 'Respond in the same language as the content. Use HTML for formatting (paragraphs, lists). '
                . 'Do not wrap the response in a code block.',
                $this->buildContext($items),
            );

            return '<div class="seal-ai-sge">' . $summary . '</div>';
        } catch (\Throwable) {
            return '';
        }
    }

    /**
     * @param array<mixed> $items
     */
    private function buildContext(array $items): string
    {
        $parts = [];
        foreach ($items as $index => $item) {
            $title = $item['title'] ?? '';
            $content = $item['content'] ?? '';
            if ($title === '' && $content === '') {
                continue;
            }
            $parts[] = sprintf("Result %d:\nTitle: %s\nContent: %s", $index + 1, $title, $content);
        }

        return "Summarize these search results:\n\n" . implode("\n\n", $parts);
    }
}
