<?php

namespace MageHost;

use Magento\User\Model\ResourceModel\User as UserResourceModel;
use Magento\User\Model\ResourceModel\User\CollectionFactory;
use N98\Magento\Command\AbstractMagentoCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class UnlockAdminCommand extends AbstractMagentoCommand
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
            ->setName('magehost:admin:unlock')
            ->setDescription(
                'Unlock all admin users that were locked by us.'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->detectMagento($output, true);
        if (!$this->initMagento()) {
            return;
        }

        if (!file_exists($_SERVER['HOME'] . '/tmp/locked_users.txt')) {
            return $output->writeln('<error>No locked users file found!</error>');
        }

        $lockedUsersRaw = file_get_contents($_SERVER['HOME'] . '/tmp/locked_users.txt');

        if (!$lockedUsersRaw) {
            return $output->writeln('<error>Locked users file is empty.</error>');
        }
        $lockedUsers = explode(',', $lockedUsersRaw);

        $collection = $this->userCollectionFactory->create();
        $collection->addFieldToFilter('main_table.user_id', ['in' => $lockedUsers]);
        $users = $collection->getItems();

        foreach ($users as $user) {
            $user->setIsActive(true);
            $this->userResourceModel->save($user);
        }

        file_put_contents($_SERVER['HOME'] . '/tmp/locked_users.txt', '');

        return $output->writeln('<info>Admin is unlocked!</info>');
    }
}
