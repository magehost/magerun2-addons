<?php

namespace MageHost;

use Magento\User\Model\ResourceModel\User as UserResourceModel;
use Magento\User\Model\ResourceModel\User\CollectionFactory;
use N98\Magento\Command\AbstractMagentoCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class LockAdminCommand extends AbstractMagentoCommand
{
    protected $userResourceModel;
    protected $userCollectionFactory;

    public function inject(UserResourceModel $userResourceModel, CollectionFactory $userCollectionFactory)
    {
        $this->userResourceModel = $userResourceModel;
        $this->userCollectionFactory = $userCollectionFactory;
    }

    protected function configure()
    {
        $this
            ->setName('magehost:admin:lock')
            ->setDescription(
                'Lock all admin users that aren\'t locked yet.'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->detectMagento($output, true);
        if (!$this->initMagento()) {
            return;
        }

        $collection = $this->userCollectionFactory->create();
        $users = $collection->getItems();

        $lockedUsers = [];

        foreach ($users as $user) {
            if ($user->getIsActive()) {
                $user->setIsActive(false);
                array_push($lockedUsers, $user->getId());
                $this->userResourceModel->save($user);
            }
        }

        if (count($lockedUsers) == 0) {
            return $output->writeln('<error>No unlocked users found!</error>');
        }

        if (!file_exists($_SERVER['HOME'] . '/tmp')) {
            mkdir($_SERVER['HOME'] . '/tmp', 0700, true);
        }

        file_put_contents($_SERVER['HOME'] . '/tmp/locked_users.txt', implode(',', $lockedUsers));

        return $output->writeln('<info>Admin is locked!</info>');
    }
}
