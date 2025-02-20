<?php

namespace App\Document;

use DateTimeImmutable;
use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;

#[ODM\Document(collection: "trends_field")]
class FieldTrend
{
    #[ODM\Id]
    private ?string $id = null;

    #[ODM\Field(type: "string")]
    private string $provider;

    #[ODM\Field(type: "date_immutable")]
    private DateTimeImmutable $timestamp;

    #[ODM\Field(type: "hash")]
    private array $counts = [];

    public function __construct(string $provider, ?DateTimeImmutable $timestamp = null, array $counts = [])
    {
        $this->provider = $provider;
        $this->timestamp = $timestamp ?? new DateTimeImmutable();
        $this->counts = $counts;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getProvider(): string
    {
        return $this->provider;
    }

    public function setProvider(string $provider): void
    {
        $this->provider = $provider;
    }

    public function getTimestamp(): DateTimeImmutable
    {
        return $this->timestamp;
    }

    public function setTimestamp(DateTimeImmutable $timestamp): void
    {
        $this->timestamp = $timestamp;
    }

    public function getCounts(): array
    {
        return $this->counts;
    }

    public function setCounts(array $counts): void
    {
        $this->counts = $counts;
    }
}
