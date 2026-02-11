<?php
/**
 * Compatibilite phpass pour la migration des mots de passe WordPress -> bcrypt
 *
 * Ce fichier fournit une fonction de verification des mots de passe haches
 * avec l'ancien format phpass ($P$ / $H$) utilise par WordPress.
 *
 * A terme, une fois tous les mots de passe migres vers bcrypt, ce fichier
 * pourra etre supprime.
 */

/**
 * Verifie un mot de passe contre un hash phpass ($P$ ou $H$)
 *
 * @param string $password Le mot de passe en clair
 * @param string $storedHash Le hash stocke en base
 * @return bool True si le mot de passe correspond
 */
function epi_phpass_check($password, $storedHash) {
    $itoa64 = './0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';

    if (strlen($storedHash) !== 34) {
        return false;
    }

    $countLog2 = strpos($itoa64, $storedHash[3]);
    if ($countLog2 < 7 || $countLog2 > 30) {
        return false;
    }
    $count = 1 << $countLog2;

    $salt = substr($storedHash, 4, 8);
    if (strlen($salt) !== 8) {
        return false;
    }

    $hash = md5($salt . $password, true);
    do {
        $hash = md5($hash . $password, true);
    } while (--$count);

    $output = substr($storedHash, 0, 12);
    $output .= _epi_encode64($hash, 16, $itoa64);

    return $output === $storedHash;
}

/**
 * Encode binaire en base64 phpass
 *
 * @param string $input Donnees binaires
 * @param int $count Nombre d'octets
 * @param string $itoa64 Alphabet base64
 * @return string Chaine encodee
 */
function _epi_encode64($input, $count, $itoa64) {
    $output = '';
    $i = 0;
    do {
        $value = ord($input[$i++]);
        $output .= $itoa64[$value & 0x3f];
        if ($i < $count) {
            $value |= ord($input[$i]) << 8;
        }
        $output .= $itoa64[($value >> 6) & 0x3f];
        if ($i++ >= $count) {
            break;
        }
        if ($i < $count) {
            $value |= ord($input[$i]) << 16;
        }
        $output .= $itoa64[($value >> 12) & 0x3f];
        if ($i++ >= $count) {
            break;
        }
        $output .= $itoa64[($value >> 18) & 0x3f];
    } while ($i < $count);

    return $output;
}
