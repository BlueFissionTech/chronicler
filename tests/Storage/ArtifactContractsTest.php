<?php

declare(strict_types=1);

namespace BlueFission\Chronicler\Tests\Storage;

use BlueFission\Chronicler\Storage\Artifact\ArtifactReference;
use BlueFission\Chronicler\Storage\Artifact\AssetInventory;
use BlueFission\Chronicler\Storage\Artifact\RetrievalMetadata;
use BlueFission\Chronicler\Storage\Reference\ResourceReference;
use PHPUnit\Framework\TestCase;

final class ArtifactContractsTest extends TestCase
{
    public function testArtifactReferenceCarriesRetrievalMetadataAndReadOnlyPacket(): void
    {
        $reference = new ArtifactReference(
            'artifact-1',
            'storage://proofs/artifact-1.json',
            ArtifactReference::TYPE_DATASET,
            'application/json',
            'sha256:abc',
            128,
            new RetrievalMetadata('signed-url', 'https://example.test/artifact-1', [], null, ['ttl' => 300]),
            ['purpose' => 'fixture']
        );

        $packet = $reference->toPacket(
            ['system' => 'artifact-store'],
            ['cursor' => 'artifact-1'],
            0.98
        )->toArray();

        $this->assertSame('storage://proofs/artifact-1.json', $reference->toArray()['uri']);
        $this->assertSame('signed-url', $reference->toArray()['retrieval']['method']);
        $this->assertSame('artifact-1', $packet['payload']['artifact']['id']);
        $this->assertFalse($packet['authority']['can_write']);
    }

    public function testAssetInventoryIndexesAndFiltersArtifactReferences(): void
    {
        $inventory = new AssetInventory();
        $media = new ArtifactReference('media-1', 'storage://media/1.png', ArtifactReference::TYPE_MEDIA);
        $document = new ArtifactReference('doc-1', 'storage://docs/1.pdf', ArtifactReference::TYPE_DOCUMENT);

        $inventory->add($media)->add($document);

        $this->assertTrue($inventory->has('media-1'));
        $this->assertSame($document, $inventory->get('doc-1'));
        $this->assertSame([$media], $inventory->byType(ArtifactReference::TYPE_MEDIA));
        $this->assertSame('storage://docs/1.pdf', $inventory->toArray()['doc-1']['uri']);
    }

    public function testResourceReferenceCapturesGraphAndDocumentLinks(): void
    {
        $graph = (new ResourceReference(
            'edge-ref-1',
            ResourceReference::KIND_GRAPH_EDGE,
            'graph://orders/edges/e1',
            'supports'
        ))->attribute('weight', 0.7);

        $document = new ResourceReference(
            'doc-ref-1',
            ResourceReference::KIND_DOCUMENT,
            'document://orders/summary',
            'describes',
            ['section' => 'summary']
        );

        $this->assertSame(ResourceReference::KIND_GRAPH_EDGE, $graph->toArray()['kind']);
        $this->assertSame(0.7, $graph->attributes()['weight']);
        $this->assertSame('summary', $document->attributes()['section']);
    }
}
