<?php
/**
 * Created by PhpStorm.
 * User: Leonardo Shinagawa
 * Date: 22/04/14
 * Time: 13:47
 */

namespace shina\controlmybudget\command;


use Fetch\Server;
use shina\controlmybudget\dataprovider\DoctrineDBAL;
use shina\controlmybudget\MailImporterService;
use shina\controlmybudget\PurchaseService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class EmailImport extends Command {

    protected function configure() {
        $this->setName('control-my-budget:import:email')
            ->addArgument('server', InputArgument::REQUIRED)
            ->addArgument('port', InputArgument::REQUIRED)
            ->addArgument('login', InputArgument::REQUIRED)
            ->addArgument('password', InputArgument::REQUIRED)
            ->addArgument('mailbox', InputArgument::REQUIRED)
            ->addArgument('firsttime', InputArgument::OPTIONAL, '', false);
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        $params = $input->getArguments();

        $imap = new Server($params['server'], $params['port']);
        $imap->setAuthentication($params['login'], $params['password']);
        $imap->setMailBox($params['mailbox']);

        $conn = $this->getHelper('db')->getConnection();
        $data_provider = new DoctrineDBAL($conn);
        $purchase_service = new PurchaseService($data_provider);

        $importer = new MailImporterService($imap, $purchase_service);
        if ($params['firsttime']) {
            $importer->firstImport();
        } else {
            $importer->import(3);
        }
    }

}