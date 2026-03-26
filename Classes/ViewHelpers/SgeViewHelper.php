<?php

declare(strict_types=1);

namespace Lochmueller\SealAi\ViewHelpers;

use Lochmueller\SealAi\AiBridge;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\SystemMessage;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\AI\Platform\Result\TextResult;
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

        $request = $this->renderingContext->getRequest();
        /** @var Site $site */
        $site = $request->getAttribute('site');
        $config = $site->getConfiguration();

        $chatModel = $config['sealAiChatModel'] ?? '';
        if ($chatModel === '') {
            return '';
        }

        $this->aiBridge->initialize($site);

        $messages = new MessageBag(
            Message::forSystem(
                'You are a helpful search assistant. Summarize the following search results into a concise, '
                . 'informative overview. Highlight the most relevant information. '
                . 'Respond in the same language as the content. Use HTML for formatting (paragraphs, lists). '
                . 'Do not wrap the response in a code block.'
            ),
            Message::ofUser($this->buildContext($items)),
        );

        try {
            /** @var TextResult $result */
            $result = $this->aiBridge->getPlatform()->invoke($chatModel, $messages)->getResult();

            return '<div class="seal-ai-sge">' . $result->getContent() . '</div>';
        } catch (\Throwable) {
            return '';
        }
    }

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
