<?php

namespace App\Services\ApiUsage;

class ApiUsageContext
{
    private ?int $deploymentId = null;

    public function deploymentId(): ?int
    {
        return $this->deploymentId;
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function forDeployment(int $deploymentId, callable $callback): mixed
    {
        $previousDeploymentId = $this->deploymentId;
        $this->deploymentId = $deploymentId;

        try {
            return $callback();
        } finally {
            $this->deploymentId = $previousDeploymentId;
        }
    }
}
