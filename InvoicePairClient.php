<?php

declare(strict_types=1);

namespace App\Models\Accounting\Flexibee;

use App\Accounting\Flexibee\BaseFlexiBeeClient;
use App\Accounting\Flexibee\Exception\CannotPairNonReceiptWithInvoiceException;
use App\Accounting\Flexibee\Exception\InvoicePairFailedException;
use App\Accounting\Flexibee\Exception\InvoiceUnpairFailedException;
use App\EntityLog\EntityLogger;
use App\Exceptions\IReasonException;
use App\Model\UnexpectedResponseException;
use App\Models\Accounting\Client\UnexpectedInvoiceTypeException;
use App\Models\Accounting\Flexibee\Dto\PairDataDto;
use App\Models\Entities\Invoices\Invoice;
use App\Models\EntityLog;
use App\Models\ExternalSystem;
use App\Models\InvoicePartialPaymentRepository;
use App\Modules\Invoice\Model\InvoiceHasNoImportIdException;
use GuzzleHttp\Exception\ClientException;
use Nette\Localization\ITranslator;
use Psr\Log\LoggerInterface;

class InvoicePairClient
{
    public function __construct(
        private BaseFlexiBeeClient $flexiBeeClient,
        private EntityLogger $entityLogger,
        private ITranslator $translator,
        private LoggerInterface $logger,
        private InvoicePartialPaymentRepository $invoicePartialPaymentRepository,
    ) {
    }

    public function pairCashDocumentWithInvoice(
        ExternalSystem $externalSystem,
        string $urlEvidence,
        Invoice $cashDocument,
        Invoice $invoice,
    ): bool {
        try {
            return $this->pairOrCrash($cashDocument, $invoice, $externalSystem, $urlEvidence);
        } catch (InvoicePairFailedException $invoicePairFailedException) {
            $dataDto = $invoicePairFailedException->getDataDto();
            $previousException = $invoicePairFailedException->getPreviousException();
            $this->logger->error($invoicePairFailedException->getMessage(), ['exception' => $invoicePairFailedException]);
            if ($previousException instanceof ClientException) {
                $this->processClientException(
                    $cashDocument,
                    $invoice,
                    $dataDto,
                    'invoice._msg_accounting.receipt_paired_with_invoice_failed',
                );
            } elseif ($previousException instanceof UnexpectedResponseException) {
                $this->processUnexpectedResponseException($cashDocument, $previousException, $dataDto);
            }
        } catch (NoPaymentRelationFoundException) {
            $this->writeErrorToEntityLog(
                cashDocument: $cashDocument,
                message: $this->translator->translate('invoice._msg_accounting.receipt_payment_not_found_paired_skipped', [
                    'invoiceNumber' => $invoice->number,
                    'receiptNumber' => $cashDocument->number,
                ]),
                type: EntityLog::TYPE_INFO,
            );
        } catch (IReasonException $reasonException) {
            $this->processReasonException($cashDocument, $reasonException);
        }

        return false;
    }

    public function unpairCashDocumentWithInvoice(
        ExternalSystem $externalSystem,
        string $urlEvidence,
        Invoice $cashDocument,
        Invoice $invoice,
    ): bool {
        try {
            return $this->unpairOrCrash($cashDocument, $invoice, $externalSystem, $urlEvidence);
        } catch (InvoiceUnpairFailedException $invoiceUnpairFailedException) {
            $dataDto = $invoiceUnpairFailedException->getDataDto();
            $previousException = $invoiceUnpairFailedException->getPreviousException();
            $this->logger->error($invoiceUnpairFailedException->getMessage(), ['exception' => $invoiceUnpairFailedException]);
            if ($previousException instanceof ClientException) {
                $this->processClientException($cashDocument, $invoice, $dataDto, 'invoice._msg_accounting.receipt_unpaired_with_invoice_failed');
            } elseif ($previousException instanceof UnexpectedResponseException) {
                $this->processUnexpectedResponseException($cashDocument, $previousException, $dataDto);
            }
        } catch (IReasonException $reasonException) {
            $this->processReasonException($cashDocument, $reasonException);
        }

        return false;
    }

    /**
     * @throws InvoiceUnpairFailedException
     * @throws IReasonException
     */
    private function unpairOrCrash(
        Invoice $cashDocument,
        Invoice $invoice,
        ExternalSystem $externalSystem,
        string $urlEvidence,
    ): bool {
        $this->entityLogger->log(
            $cashDocument,
            EntityLog::ACTION_ACCOUNTING_SYNC,
            EntityLog::TYPE_INFO,
            $this->translator->translate(
                'invoice._msg_accounting.receipt_unpaired_with_invoice_started',
                [
                    'receiptNumber' => $cashDocument->number,
                    'invoiceNumber' => $invoice->number,
                    'systemName' => $externalSystem->_title(),
                ],
            ),
        );


        if ($invoice->_getImportId($externalSystem) === null) {
            throw new InvoiceHasNoImportIdException($externalSystem, $invoice->_title());
        }

        if ($cashDocument->isCashDocument() === false) {
            throw new UnexpectedInvoiceTypeException($cashDocument);
        }

        if ($invoice->isCashDocument() === true) {
            throw new CannotPairNonReceiptWithInvoiceException($invoice->type);
        }

        try {
            $data = [
                'winstrom' => [
                    'banka' => [
                        'id' => $cashDocument->_getImportId($externalSystem),
                        'odparovani' => [
                            'uhrazovanaFak' => [
                                'id' => $invoice->_getImportId($externalSystem),
                                '@type' => $urlEvidence,
                            ],
                        ],
                    ],
                ],
            ];


            $path = sprintf('%s/%s.json', $this->flexiBeeClient->getPath(), $urlEvidence);
            $jsonData = json_encode($data);
            assert(is_string($jsonData));

            $response = $this->flexiBeeClient->sendRequest('PUT', $path, [], $jsonData);
            $jsonResponse = $response->getBody()->getContents();
            $responseData = json_decode($jsonResponse, true);

            if (!isset($responseData['winstrom']['success']) || $responseData['winstrom']['success'] !== 'true') {
                throw new UnexpectedResponseException($responseData['winstrom']['success']);
            }

            $this->entityLogger->log(
                $cashDocument,
                EntityLog::ACTION_ACCOUNTING_SYNC,
                EntityLog::TYPE_SUCCESS,
                $this->translator->translate(
                    'invoice._msg_accounting.receipt_unpaired_with_invoice_success',
                    [
                        'receiptNumber' => $cashDocument->number,
                        'invoiceNumber' => $invoice->number,
                    ],
                ),
            );

            return true;
        } catch (ClientException $e) {
            $responseContent = $e->getResponse()->getBody()->getContents();
            $responseData = json_decode($responseContent, true);
            $dto = new PairDataDto($data, $responseData);
            throw new InvoiceUnpairFailedException($dto, $e);
        } catch (UnexpectedResponseException $e) {
            $dto = new PairDataDto($data, $responseData ?? []);
            throw new InvoiceUnpairFailedException($dto, $e);
        }
    }


    /**
     * @throws InvoicePairFailedException
     * @throws IReasonException
     */
    private function pairOrCrash(
        Invoice $cashDocument,
        Invoice $invoice,
        ExternalSystem $externalSystem,
        string $urlEvidence,
    ): bool {
        $this->entityLogger->log(
            $cashDocument,
            EntityLog::ACTION_ACCOUNTING_SYNC,
            EntityLog::TYPE_INFO,
            $this->translator->translate(
                'invoice._msg_accounting.receipt_paired_with_invoice_started',
                [
                    'receiptNumber' => $cashDocument->number,
                    'invoiceNumber' => $invoice->number,
                    'systemName' => $externalSystem->_title(),
                ],
            ),
        );

        $amount = $this->getPaymentPrice($invoice, $cashDocument);
        if ($amount === null) {
            throw new NoPaymentRelationFoundException(sprintf(
                'For invoice %s and cashDocument %s no payment was found',
                $invoice->_title(),
                $cashDocument->_title(),
            ));
        }

        if ($invoice->_getImportId($externalSystem) === null) {
            throw new InvoiceHasNoImportIdException($externalSystem, $invoice->_title());
        }

        if ($cashDocument->isCashDocument() === false) {
            throw new UnexpectedInvoiceTypeException($cashDocument);
        }

        if ($invoice->isCashDocument() === true) {
            throw new CannotPairNonReceiptWithInvoiceException($invoice->type);
        }

        try {
            $data = [
                'winstrom' => [
                    'banka' => [
                        'id' => $cashDocument->_getImportId($externalSystem),
                        'sparovani' => [
                            'uhrazovanaFak' => [
                                'id' => $invoice->_getImportId($externalSystem),
                                '@type' => $urlEvidence,
                                '@castka' => $amount,
                            ],
                            'zbytek' => 'castecnaUhradaNeboZauctovat',
                        ],
                    ],
                ],
            ];


            $path = sprintf('%s/%s.json', $this->flexiBeeClient->getPath(), $urlEvidence);
            $jsonData = json_encode($data);
            assert(is_string($jsonData));

            $response = $this->flexiBeeClient->sendRequest('PUT', $path, [], $jsonData);
            $jsonResponse = $response->getBody()->getContents();
            $responseData = json_decode($jsonResponse, true);

            if (!isset($responseData['winstrom']['success']) || $responseData['winstrom']['success'] !== 'true') {
                throw new UnexpectedResponseException($responseData['winstrom']['success']);
            }

            $this->entityLogger->log(
                $cashDocument,
                EntityLog::ACTION_ACCOUNTING_SYNC,
                EntityLog::TYPE_SUCCESS,
                $this->translator->translate(
                    'invoice._msg_accounting.receipt_paired_with_invoice_success',
                    [
                        'receiptNumber' => $cashDocument->number,
                        'invoiceNumber' => $invoice->number,
                    ],
                ),
                EntityLog::DATA_TYPE_FLEXIBEE_ERROR,
                [
                    'request' => $data,
                    'response' => $responseData,
                ],
            );

            return true;
        } catch (ClientException $e) {
            $responseContent = $e->getResponse()->getBody()->getContents();
            $responseData = json_decode($responseContent, true);
            $dto = new PairDataDto($data, $responseData);
            throw new InvoicePairFailedException($dto, $e);
        } catch (UnexpectedResponseException $e) {
            $dto = new PairDataDto($data, $responseData ?? []);
            throw new InvoicePairFailedException($dto, $e);
        }
    }

    private function processClientException(
        Invoice $cashDocument,
        Invoice $invoice,
        PairDataDto $dataDto,
        string $msg,
    ): void {
        $this->entityLogger->log(
            $cashDocument,
            EntityLog::ACTION_ACCOUNTING_SYNC,
            EntityLog::TYPE_DANGER,
            $this->translator->translate(
                $msg,
                [
                    'receiptNumber' => $cashDocument->number,
                    'invoiceNumber' => $invoice->number,
                ],
            ),
            EntityLog::DATA_TYPE_FLEXIBEE_ERROR,
            [
                'response' => $dataDto->getResponseData(),
                'request' => $dataDto->getRequestData(),
            ],
        );
    }

    private function processUnexpectedResponseException(
        Invoice $cashDocument,
        UnexpectedResponseException $unexpectedResponseException,
        PairDataDto $dataDto,
    ): void {
        $this->entityLogger->log(
            $cashDocument,
            EntityLog::ACTION_ACCOUNTING_SYNC,
            EntityLog::TYPE_DANGER,
            $unexpectedResponseException->getReasonMessage($this->translator),
            EntityLog::DATA_TYPE_FLEXIBEE_ERROR,
            [
                'response' => $dataDto->getResponseData(),
                'request' => $dataDto->getRequestData(),
            ],
        );
    }

    private function processReasonException(
        Invoice $cashDocument,
        IReasonException $reasonException,
    ): void {
        $this->writeErrorToEntityLog(
            cashDocument: $cashDocument,
            message: $reasonException->getReasonMessage($this->translator),
            type: EntityLog::TYPE_DANGER,
        );
    }

    private function writeErrorToEntityLog(
        Invoice $cashDocument,
        string $message,
        string $type,
    ): void {
        $this->entityLogger->log(
            $cashDocument,
            EntityLog::ACTION_ACCOUNTING_SYNC,
            $type,
            $message,
            EntityLog::DATA_TYPE_FLEXIBEE_ERROR,
        );
    }

    private function getPaymentPrice(
        Invoice $invoice,
        Invoice $cashDocument,
    ): ?float {
        $payment = $this->invoicePartialPaymentRepository->findPaymentByCashDocument($invoice, $cashDocument);
        if ($payment != null) {
            return $payment->price;
        }

        return null;
    }
}
