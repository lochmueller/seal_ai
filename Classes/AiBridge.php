<?php

declare(strict_types=1);

namespace Lochmueller\SealAi;

use Lochmueller\SealAi\Chat\ChatInterface;
use Symfony\AI\Store\Document\VectorizerInterface;
use Symfony\AI\Store\ManagedStoreInterface;
use Symfony\AI\Store\StoreInterface;

/**
 * Store, vectorizer and chat of exactly one site. Created by the AiBridgeFactory and never
 * shared between sites, so a process that handles multiple sites (indexing, CLI, dashboard)
 * can not write the documents of one site into the store of another.
 */
readonly class AiBridge
{
    public function __construct(
        private StoreInterface&ManagedStoreInterface $store,
        private VectorizerInterface                  $vectorizer,
        private VectorizerInterface                  $cachedVectorizer,
        private ?ChatInterface                       $chat = null,
    ) {}

    public function getStore(): StoreInterface&ManagedStoreInterface
    {
        return $this->store;
    }

    public function getVectorizer(): VectorizerInterface
    {
        return $this->vectorizer;
    }

    /**
     * Vectorizer for indexing: wrapped in the embedding cache (if enabled), so unchanged content costs no tokens.
     */
    public function getCachedVectorizer(): VectorizerInterface
    {
        return $this->cachedVectorizer;
    }

    /**
     * Chat for AI-generated search summaries (SGE). Null if no "sealAiChatModel" is configured.
     */
    public function getChat(): ?ChatInterface
    {
        return $this->chat;
    }
}
