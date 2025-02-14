<?php

namespace App\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;

#[ODM\Document(collection: "reports_completeness")]
class CompletenessReport
{
    #[ODM\Id]
    private ?string $id = null;

    #[ODM\Field(type: "string")]
    private string $provider;

    #[ODM\Field(type: "int")]
    private int $total = 0;

    #[ODM\Field(type: "int")]
    private int $minimum = 0;

    #[ODM\Field(type: "int")]
    private int $basic = 0;

    #[ODM\Field(type: "int")]
    private int $rightsData = 0;

    #[ODM\Field(type: "int")]
    private int $rightsWork = 0;

    #[ODM\Field(type: "int")]
    private int $rightsDigitalRepresentation = 0;

    public function __construct(string $provider)
    {
        $this->provider = $provider;
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

    public function getTotal(): int
    {
        return $this->total;
    }

    public function setTotal(int $total): void
    {
        $this->total = $total;
    }

    public function incrementTotal(): void
    {
        $this->total++;
    }

    public function getMinimum(): int
    {
        return $this->minimum;
    }

    public function setMinimum(int $minimum): void
    {
        $this->minimum = $minimum;
    }

    public function incrementMinimum(): void
    {
        $this->minimum++;
    }

    public function getBasic(): int
    {
        return $this->basic;
    }

    public function setBasic(int $basic): void
    {
        $this->basic = $basic;
    }

    public function incrementBasic(): void
    {
        $this->basic++;
    }

    public function getRightsData(): int
    {
        return $this->rightsData;
    }

    public function setRightsData(int $rightsData): void
    {
        $this->rightsData = $rightsData;
    }

    public function incrementRightsData(): void
    {
        $this->rightsData++;
    }

    public function getRightsWork(): int
    {
        return $this->rightsWork;
    }

    public function setRightsWork(int $rightsWork): void
    {
        $this->rightsWork = $rightsWork;
    }

    public function incrementRightsWork(): void
    {
        $this->rightsWork++;
    }

    public function getRightsDigitalRepresentation(): int
    {
        return $this->rightsDigitalRepresentation;
    }

    public function setRightsDigitalRepresentation(int $rightsDigitalRepresentation): void
    {
        $this->rightsDigitalRepresentation = $rightsDigitalRepresentation;
    }

    public function incrementRightsDigitalRepresentation(): void
    {
        $this->rightsDigitalRepresentation++;
    }
}
