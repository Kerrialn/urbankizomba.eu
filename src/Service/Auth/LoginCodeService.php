<?php

declare(strict_types=1);

namespace App\Service\Auth;

use App\Entity\LoginCode;
use App\Enum\VerificationTypeEnum;
use App\Repository\LoginCodeRepository;
use App\Verification\DestinationNormalizer;
use App\Verification\Sender\VerificationSenderResolver;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Random\RandomException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Issues and verifies one-time login codes.
 *
 * Delivery is delegated to whichever {@see VerificationSenderInterface} supports
 * the destination type, so adding SMS login later means passing
 * VerificationTypeEnum::PHONE here — nothing in this class changes.
 */
final readonly class LoginCodeService
{
    private const int CODE_LENGTH = 6;

    private const int TTL_MINUTES = 10;

    public function __construct(
        private LoginCodeRepository $loginCodeRepository,
        private EntityManagerInterface $entityManager,
        private VerificationSenderResolver $senderResolver,
        private DestinationNormalizer $destinationNormalizer,
        #[Autowire('%kernel.secret%')]
        private string $secret,
    ) {
    }

    /**
     * @throws RandomException
     */
    public function issue(VerificationTypeEnum $type, string $rawDestination): void
    {
        $destination = $this->destinationNormalizer->normalize($type, $rawDestination);

        // Retire any outstanding code first: only the newest email should work.
        $this->loginCodeRepository->consumeAllFor($type, $destination);

        $code = $this->generateCode();

        $loginCode = new LoginCode(
            type: $type,
            destination: $destination,
            codeHash: $this->hash($destination, $code),
            expiresAt: new DateTimeImmutable(sprintf('+%d minutes', self::TTL_MINUTES)),
        );

        $this->entityManager->persist($loginCode);
        $this->entityManager->flush();

        $this->senderResolver->for($type)->send($destination, $code);
    }

    /**
     * Returns the normalised destination on success, null on any failure.
     *
     * Every failure path looks identical to the caller so the response cannot
     * distinguish "no code outstanding" from "wrong code".
     */
    public function verify(VerificationTypeEnum $type, string $rawDestination, string $submittedCode): ?string
    {
        $destination = $this->destinationNormalizer->normalize($type, $rawDestination);
        $now = new DateTimeImmutable();

        $loginCode = $this->loginCodeRepository->findLatestUsable($type, $destination, $now);

        if (! $loginCode instanceof LoginCode) {
            return null;
        }

        if (! hash_equals($loginCode->getCodeHash(), $this->hash($destination, trim($submittedCode)))) {
            $loginCode->recordFailedAttempt();
            $this->entityManager->flush();

            return null;
        }

        $loginCode->consume();
        $this->entityManager->flush();

        return $destination;
    }

    /**
     * Keyed over the destination as well as the code, so a stolen hash cannot be
     * replayed against a different address.
     */
    private function hash(string $destination, string $code): string
    {
        return hash_hmac('sha256', $destination . '|' . $code, $this->secret);
    }

    /**
     * @throws RandomException
     */
    private function generateCode(): string
    {
        return str_pad(
            (string) random_int(0, 10 ** self::CODE_LENGTH - 1),
            self::CODE_LENGTH,
            '0',
            STR_PAD_LEFT,
        );
    }
}
