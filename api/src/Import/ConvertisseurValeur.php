<?php

declare(strict_types=1);

namespace App\Import;

/**
 * Normalise les valeurs brutes des fichiers clients, qui viennent de tableurs
 * remplis a la main : accents, espaces insecables, symboles monetaires,
 * formats de date francais ou ISO.
 */
final class ConvertisseurValeur
{
    private const FORMATS_DATE = ['d/m/Y', 'd-m-Y', 'd.m.Y', 'Y-m-d', 'd/m/y', 'Y/m/d'];

    /**
     * Cle de comparaison d'un intitule de colonne : minuscules, sans accent,
     * sans separateur. "N° Client" et "numero_client" donnent la meme cle.
     */
    public static function normaliserEntete(string $entete): string
    {
        // Le symbole degre doit disparaitre avant la translitteration, qui le
        // transforme sinon en "^0" et ferait echouer la reconnaissance de "N° Client".
        $sansSymbole = str_replace(['°', 'º', '·'], ' ', $entete);

        return strtolower((string) preg_replace('/[^a-zA-Z0-9]/', '', self::retirerAccents($sansSymbole)));
    }

    public static function retirerAccents(string $valeur): string
    {
        $translitere = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $valeur);

        return false !== $translitere ? $translitere : $valeur;
    }

    public static function chaine(?string $valeur): ?string
    {
        if (null === $valeur) {
            return null;
        }

        // Les exports de tableur contiennent souvent des espaces insecables.
        $nettoye = trim(str_replace(["\u{00A0}", "\u{202F}"], ' ', $valeur));

        return '' !== $nettoye ? $nettoye : null;
    }

    /**
     * Convertit "1 234,56 €", "1234.56" ou "1.234,56" en chaine decimale exploitable
     * par une colonne NUMERIC. Retourne null si la valeur n'est pas numerique.
     */
    public static function decimal(?string $valeur): ?string
    {
        $nettoye = self::chaine($valeur);

        if (null === $nettoye) {
            return null;
        }

        $nettoye = (string) preg_replace('/[^0-9,.\-]/', '', $nettoye);

        if ('' === $nettoye || '-' === $nettoye) {
            return null;
        }

        $positionVirgule = strrpos($nettoye, ',');
        $positionPoint = strrpos($nettoye, '.');

        if (false !== $positionVirgule && false !== $positionPoint) {
            // Le separateur decimal est le dernier des deux ; l'autre groupe les milliers.
            $separateurDecimal = $positionVirgule > $positionPoint ? ',' : '.';
            $separateurMilliers = ',' === $separateurDecimal ? '.' : ',';
            $nettoye = str_replace($separateurMilliers, '', $nettoye);
            $nettoye = str_replace($separateurDecimal, '.', $nettoye);
        } elseif (false !== $positionVirgule) {
            $nettoye = str_replace(',', '.', $nettoye);
        }

        return is_numeric($nettoye) ? number_format((float) $nettoye, 2, '.', '') : null;
    }

    public static function date(?string $valeur): ?\DateTimeImmutable
    {
        $nettoye = self::chaine($valeur);

        if (null === $nettoye) {
            return null;
        }

        foreach (self::FORMATS_DATE as $format) {
            $date = \DateTimeImmutable::createFromFormat('!'.$format, $nettoye);

            if (false !== $date) {
                return $date;
            }
        }

        return null;
    }

    /**
     * Retrouve un cas d'enumeration a partir d'un libelle libre.
     *
     * @param array<string, string> $correspondances cle normalisee => valeur de l'enumeration
     */
    public static function versEnumeration(?string $valeur, array $correspondances): ?string
    {
        $nettoye = self::chaine($valeur);

        if (null === $nettoye) {
            return null;
        }

        return $correspondances[self::normaliserEntete($nettoye)] ?? null;
    }
}
