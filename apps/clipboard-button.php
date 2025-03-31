<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

// Function to restore terminal state
function restoreTerminal($mode) {
    echo "\033[?25h"; // Show cursor
    echo "\033[?1003l"; // Disable mouse tracking
    echo "\033[?7h"; // Re-enable line wrapping
    echo "\033[0m"; // Reset all formatting
    shell_exec('stty ' . $mode);
}

// Store initial terminal mode before any modifications
$initialTtyMode = shell_exec('stty -g');

// Set up signal handler
pcntl_async_signals(true);
pcntl_signal(SIGINT, function() use ($initialTtyMode) {
    echo "\nCaught SIGINT, exiting...\n";
    restoreTerminal($initialTtyMode);
    exit;
});

// Register shutdown function
register_shutdown_function(function() use ($initialTtyMode) {
    restoreTerminal($initialTtyMode);
});

// Hide the terminal cursor
echo "\033[?25l";

// Disable terminal echo and set raw mode
shell_exec('stty -icanon -echo');

// Enable comprehensive mouse tracking
echo "\033[?1003h"; // Enable any-event tracking (tracks all mouse events)

// Check terminal color support
function checkColorSupport() {
    $term = getenv('TERM');
    $colorTerm = getenv('COLORTERM');

    if (strpos($term, '256color') !== false || $colorTerm === 'truecolor' || $colorTerm === '24bit') {
        return 256; // 256 color support
    } elseif (strpos($term, 'color') !== false) {
        return 16; // Basic color support
    } else {
        return 0; // No color support
    }
}

$colorSupport = checkColorSupport();
echo "\033[2J"; // Clear screen
echo "\033[H";  // Move cursor to home position

// Function to get terminal dimensions
function getTerminalDimensions() {
    $dimensions = [];
    if (preg_match('/(\d+) (\d+)/s', shell_exec('stty size'), $matches)) {
        $dimensions = [(int)$matches[2], (int)$matches[1]]; // width, height
    } else {
        // Fallback
        $dimensions = [80, 24];
    }

    return $dimensions;
}

// Function to center text horizontally
function centerText(string $text, int $width, int $extraPadding = 0): string {
    // Strip ANSI escape sequences for length calculation
    $cleanText = preg_replace('/\033\[[^m]*m/', '', $text);

    // Simple text length calculation plus any extra padding adjustment
    $textLength = mb_strlen($cleanText);

    // Calculate padding to center
    $paddingEitherSide = max(0, (int)floor(($width - $textLength) / 2)) + $extraPadding;
    return str_repeat(' ', $paddingEitherSide) . $text;
}

// Function to create a gradient colored text
function gradientText(string $text, int $startColor, int $endColor, int $colorSupport): string {
    if ($colorSupport < 256) {
        // Fallback for terminals with limited color support
        return "\033[1;36m$text\033[0m"; // Bright cyan
    }

    $result = '';
    $length = mb_strlen($text);

    for ($i = 0; $i < $length; $i++) {
        $ratio = $i / ($length - 1);
        $colorCode = (int)($startColor + ($endColor - $startColor) * $ratio);
        $result .= "\033[38;5;{$colorCode}m" . mb_substr($text, $i, 1);
    }

    return $result . "\033[0m";
}

// Function to create a solid colored button with specified background color
function solidButton(string $text, int $width, int $backgroundColor, int $colorSupport): string {
    if ($colorSupport < 256) {
        // Fallback for terminals with limited color support
        return "\033[1;37;44m" . str_pad($text, $width, ' ', STR_PAD_BOTH) . "\033[0m"; // White on blue
    }

    $text = mb_str_pad($text, $width, ' ', STR_PAD_BOTH);
    return "\033[48;5;{$backgroundColor}m\033[38;5;255m" . $text . "\033[0m";
}

// Function to copy text to clipboard using OSC 52 escape sequence
function copyToClipboard($text) {
    $encodedText = base64_encode($text);
    echo "\033]52;c;{$encodedText}\007";
    return true;
}

// Get terminal dimensions
list($termWidth, $termHeight) = getTerminalDimensions();

// The text to display and potentially copy
$titleText = "How cool is clipboard support in the terminal?!";
$clipboardText = "Very bloody cool!";

// Button properties
$buttonText = "[ 📋 Copy to Clipboard ]";
$buttonWidth = 30;

// Button colors - vertical gradient
// Normal state: light blue to darker blue - smoother gradient
$buttonTopColor = 39;
$buttonMiddleColor = 38;
$buttonBottomColor = 37;

// Hover state: darker blue to very dark blue - smoother gradient
$hoverTopColor = 33;     // Dark blue
$hoverMiddleColor = 32;  // Darker blue
$hoverBottomColor = 31;  // Very dark blue

// Setup display area
$textLines = [];
$wrappedText = wordwrap($titleText, $termWidth - 10, "\n", true);
$lines = explode("\n", $wrappedText);
foreach ($lines as $line) {
    $textLines[] = centerText($line, $termWidth);
}

// Calculate vertical center position
$totalContentHeight = count($textLines) + 8; // Text lines + button with padding + instructions
$textStartRow = max(3, (int)(($termHeight - $totalContentHeight) / 2));
$buttonRow = $textStartRow + count($textLines) + 3; // More space between text and button

// Button position and size
$buttonLeft = (int)(($termWidth - $buttonWidth) / 2);
$buttonRight = $buttonLeft + $buttonWidth;

// Draw the UI initially
function drawUI($textLines, $buttonText, $buttonWidth, $textStartRow, $buttonRow, $termWidth, $colorSupport, $isButtonHovered = false,
                $buttonTopColor, $buttonMiddleColor, $buttonBottomColor,
                $hoverTopColor, $hoverMiddleColor, $hoverBottomColor) {
    echo "\033[H"; // Move cursor to home position

    // Display color support info
    echo "\033[2;2H";
    $colorSupportText = "Terminal color support: ";
    if ($colorSupport >= 256) {
        $colorSupportText .= "\033[38;5;46m256 colors\033[0m"; // Bright green
    } elseif ($colorSupport >= 16) {
        $colorSupportText .= "\033[32mBasic colors\033[0m"; // Green
    } else {
        $colorSupportText .= "\033[31mNo color support\033[0m"; // Red
    }

    echo centerText($colorSupportText, $termWidth);
    // Display text
    for ($i = 0; $i < count($textLines); $i++) {
        echo "\033[" . ($textStartRow + $i) . ";1H";
        echo $textLines[$i];
    }

    // Display button with vertical padding
    if ($isButtonHovered) {
        // Top padding with hover color gradient
        echo "\033[" . ($buttonRow - 1) . ";1H";
        echo centerText(solidButton(str_repeat(' ', $buttonWidth - 4), $buttonWidth, $hoverTopColor, $colorSupport), $termWidth);

        // Button with hover color gradient
        echo "\033[{$buttonRow};1H";
        echo centerText(solidButton($buttonText, $buttonWidth - 1, $hoverMiddleColor, $colorSupport), $termWidth);

        // Bottom padding with hover color gradient
        echo "\033[" . ($buttonRow + 1) . ";1H";
        echo centerText(solidButton(str_repeat(' ', $buttonWidth - 4), $buttonWidth, $hoverBottomColor, $colorSupport), $termWidth);
    } else {
        // Top padding with normal color gradient
        echo "\033[" . ($buttonRow - 1) . ";1H";
        echo centerText(solidButton(str_repeat(' ', $buttonWidth - 4), $buttonWidth, $buttonTopColor, $colorSupport), $termWidth);

        // Button with normal color gradient
        echo "\033[{$buttonRow};1H";
        echo centerText(solidButton($buttonText, $buttonWidth - 1, $buttonMiddleColor, $colorSupport), $termWidth);

        // Bottom padding with normal color gradient
        echo "\033[" . ($buttonRow + 1) . ";1H";
        echo centerText(solidButton(str_repeat(' ', $buttonWidth - 4), $buttonWidth, $buttonBottomColor, $colorSupport), $termWidth);
    }
}

// Draw the initial UI
drawUI(
    $textLines, $buttonText, $buttonWidth, $textStartRow, $buttonRow, $termWidth, $colorSupport, false,
    $buttonTopColor, $buttonMiddleColor, $buttonBottomColor,
    $hoverTopColor, $hoverMiddleColor, $hoverBottomColor
);

// Main loop
$isButtonHovered = false;
$isTextCopied = false;

while (true) {
    // Check for signals at the start of each loop
    pcntl_signal_dispatch();

    // Set STDIN to non-blocking mode
    stream_set_blocking(STDIN, false);

    // Read input until newline or EOF
    $input = fgets(STDIN, 1024);
    if ($input === false) {
        usleep(100000); // 100ms delay to not hog CPU
        continue;
    }

    // Split input into individual ANSI sequences
    $sequences = preg_split('/(?=\033\[)/', $input, -1, PREG_SPLIT_NO_EMPTY);

    foreach ($sequences as $sequence) {
        // Check if it's a mouse event (starts with ^[[M)
        if (str_starts_with($sequence, "\033[M")) {
            $button = ord($sequence[3]);
            $x = ord($sequence[4]) - 32; // Convert to actual terminal coordinates
            $y = ord($sequence[5]) - 32; // Convert to actual terminal coordinates

            // Check if mouse is over the button (including vertical padding)
            $wasButtonHovered = $isButtonHovered;
            $isButtonHovered = ($y >= $buttonRow - 1 && $y <= $buttonRow + 1 && $x >= $buttonLeft && $x <= $buttonRight);

            // Redraw UI if hover state changed
            if ($wasButtonHovered != $isButtonHovered) {
                drawUI(
                    $textLines, $buttonText, $buttonWidth, $textStartRow, $buttonRow, $termWidth, $colorSupport, $isButtonHovered,
                    $buttonTopColor, $buttonMiddleColor, $buttonBottomColor,
                    $hoverTopColor, $hoverMiddleColor, $hoverBottomColor
                );
            }

            // Handle mouse button events
            if ($button >= 32 && $button <= 34 && $isButtonHovered) { // Mouse down over button
                $wasTextCopied = $isTextCopied;
                $isTextCopied = copyToClipboard($clipboardText);

                // Show copy confirmation
                if ($isTextCopied) {
                    echo "\033[" . ($buttonRow + 2) . ";1H";
                    echo centerText("\033[32mText copied to clipboard! ✅\033[0m", $termWidth);

                    // Clear the confirmation after 2 seconds
                    usleep(2000000); // 2 seconds
                    echo "\033[" . ($buttonRow + 2) . ";1H";
                    echo str_repeat(' ', $termWidth);
                }
            }
        }
    }

    // Small sleep to prevent CPU spinning
    usleep(50000); // 50ms
}
