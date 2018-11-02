<?php

namespace CloudCreativity\LaravelJsonApi\Tests\Integration\Queue;

use CloudCreativity\LaravelJsonApi\Queue\ClientJob;
use CloudCreativity\LaravelJsonApi\Tests\Integration\TestCase;

class QueueJobsTest extends TestCase
{

    /**
     * @var string
     */
    protected $resourceType = 'queue-jobs';

    public function testListAll()
    {
        $jobs = factory(ClientJob::class, 2)->create();
        // this one should not appear in results as it is for a different resource type.
        factory(ClientJob::class)->create(['resource_type' => 'foo']);

        $this->getJsonApi('/api/v1/downloads/queue-jobs')
            ->assertFetchedMany($jobs);
    }

    public function testReadPending()
    {
        $job = factory(ClientJob::class)->create();
        $expected = $this->serialize($job);

        $this->getJsonApi($expected['links']['self'])
            ->assertFetchedOneExact($expected);
    }

    /**
     * When job process is done, the request SHOULD return a status 303 See other
     * with a link in Location header.
     */
    public function testReadNotPending()
    {
       $job = factory(ClientJob::class)->states('success')->create([
           'resource_id' => '5b08ebcb-114b-4f9e-a0db-bd8bd046e74c',
       ]);

       $location = "http://localhost/api/v1/downloads/5b08ebcb-114b-4f9e-a0db-bd8bd046e74c";

       $this->getJsonApi($this->jobUrl($job))
           ->assertStatus(303)
           ->assertHeader('Location', $location);
    }

    /**
     * If the asynchronous process does not have a location, a See Other response cannot be
     * returned. In this scenario, we expect the job to be serialized.
     */
    public function testReadNotPendingCannotSeeOther()
    {
        $job = factory(ClientJob::class)->states('success')->create();
        $expected = $this->serialize($job);

        $this->getJsonApi($this->jobUrl($job))
            ->assertFetchedOneExact($expected)
            ->assertHeaderMissing('Location');
    }

    public function testReadNotFound()
    {
        $job = factory(ClientJob::class)->create(['resource_type' => 'foo']);

        $this->getJsonApi($this->jobUrl($job, 'downloads'))
            ->assertStatus(404);
    }

    /**
     * @param ClientJob $job
     * @param string|null $resourceType
     * @return string
     */
    private function jobUrl(ClientJob $job, string $resourceType = null): string
    {
        $resourceType = $resourceType ?: $job->resource_type;

        return "/api/v1/{$resourceType}/queue-jobs/{$job->getRouteKey()}";
    }

    /**
     * Get the expected resource object for a client job model.
     *
     * @param ClientJob $job
     * @return array
     */
    private function serialize(ClientJob $job): array
    {
        $self = "http://localhost" . $this->jobUrl($job);
        $format = 'Y-m-d\TH:i:s.uP';

        return [
            'type' => 'queue-jobs',
            'id' => (string) $job->getRouteKey(),
            'attributes' => [
                'attempts' => $job->attempts,
                'created-at' => $job->created_at->format($format),
                'completed-at' => $job->completed_at ? $job->completed_at->format($format) : null,
                'failed' => $job->failed,
                'resource-type' => 'downloads',
                'timeout' => $job->timeout,
                'timeout-at' => $job->timeout_at ? $job->timeout_at->format($format) : null,
                'tries' => $job->tries,
                'updated-at' => $job->updated_at->format($format),
            ],
            'relationships' => [
                'resource' => [
                    'links' => [
                        'self' => "{$self}/relationships/resource",
                        'related' => "{$self}/resource",
                    ],
                ],
            ],
            'links' => [
                'self' => $self,
            ],
        ];
    }
}
