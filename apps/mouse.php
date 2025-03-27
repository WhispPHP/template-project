<?php

// TODO: Fix Ctrl+C not working through SSH

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use function Laravel\Prompts\clear;

clear();
// echo "Listening to mouse movements...\n";

// Store initial terminal mode before any modifications
$initialTtyMode = shell_exec('stty -g');

// Function to restore terminal state
function restoreTerminal($mode) {
    echo "\033[?25h"; // Show cursor
    echo "\033[?1003l"; // Disable mouse tracking
    shell_exec('stty ' . $mode);
}

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

// Disable terminal echo
shell_exec('stty -icanon -echo');

// Enable comprehensive mouse tracking
echo "\033[?1003h"; // Enable any-event tracking (tracks all mouse events)

$isClicked = false;
$lastX = 0;
$lastY = 0;
$colorIndex = 0;
$isMouseDown = false;
$isDragging = false;
$dragStartX = 0;
$dragStartY = 0;
$path = [];

// Define colors (ANSI color codes)
$colors = [
    "\033[32m", // Green
    "\033[34m", // Blue
    "\033[33m", // Yellow
    "\033[35m", // Magenta
    "\033[36m", // Cyan
];

class ShrapnelPiece {
    public float $x;
    public float $y;
    public float $angle;
    public float $speed;
    public int $framesLeft;
    public string $color;

    public function __construct(float $x, float $y, float $angle, float $speed, int $framesLeft, string $color) {
        $this->x = $x;
        $this->y = $y;
        $this->angle = $angle;
        $this->speed = $speed;
        $this->framesLeft = $framesLeft;
        $this->color = $color;
    }

    public function update(): void {
        $this->x += cos($this->angle) * $this->speed;
        $this->y += sin($this->angle) * $this->speed;
        $this->framesLeft--;
    }
}

$shrapnel = [];

// Function to draw cursor
function drawCursor($x, $y, $color) {
    echo "\033[{$y};{$x}H";
    echo $color;
    echo "◎";
    echo "\033[0m";
}

// Function to draw a dot at a position
function drawDot($x, $y, $color) {
    echo "\033[{$y};{$x}H";
    echo $color;
    echo "·";
    echo "\033[0m";
}

// Function to draw shrapnel piece
function drawShrapnel($x, $y, $oldX, $oldY, $color) {
    // Clear old position
    echo "\033[" . $oldY . ";" . $oldX . "H ";
    // Draw new position
    echo "\033[" . (int)$y . ";" . (int)$x . "H";
    echo $color;
    echo "•";
    echo "\033[0m";
}

while (true) {
    // Check for signals at the start of each loop
    pcntl_signal_dispatch();

    // Update and draw shrapnel
    foreach ($shrapnel as $key => $piece) {
        $oldX = (int)$piece->x;
        $oldY = (int)$piece->y;
        $piece->update();
        drawShrapnel($piece->x, $piece->y, $oldX, $oldY, $piece->color);

        if ($piece->framesLeft <= 0) {
            // Clear final position when piece is removed
            echo "\033[" . (int)$piece->y . ";" . (int)$piece->x . "H ";
            unset($shrapnel[$key]);
        }
    }

    // Set STDIN to non-blocking mode
    stream_set_blocking(STDIN, false);

    // Read input until newline or EOF
    $input = fgets(STDIN, 1024);
    if ($input === false) {
        usleep(100000); // 100ms
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

            // Handle mouse button events
            if ($button >= 32 && $button <= 34) { // Mouse down
                $isMouseDown = true;
                $dragStartX = $x;
                $dragStartY = $y;
                $path = []; // Clear previous path
                $colorIndex = ($colorIndex + 1) % count($colors);

                // Create shrapnel pieces
                $baseAngle = rand(0, 360) * (M_PI / 180); // Random base angle in radians
                $minAngleBetween = 2 * M_PI / 3; // 120 degrees minimum between pieces

                for ($i = 0; $i < 3; $i++) {
                    // Calculate angle ensuring minimum separation
                    $angle = $baseAngle + ($i * $minAngleBetween);
                    $speed = 0.7; // Speed of movement
                    $framesLeft = 8; // Number of frames before disappearing
                    $shrapnel[] = new ShrapnelPiece($x, $y, $angle, $speed, $framesLeft, $colors[$colorIndex]);
                }
            } elseif ($button >= 35 && $button <= 37) { // Mouse up
                $isMouseDown = false;
                $isDragging = false;
            }

            // Check if we should start dragging
            if ($isMouseDown && !$isDragging) {
                $dx = abs($x - $dragStartX);
                $dy = abs($y - $dragStartY);
                if ($dx > 1 || $dy > 1) {
                    $isDragging = true;
                }
            }

            // Clear previous cursor position
            if ($lastX > 0 && $lastY > 0) {
                // Clear all three rows of the previous box
                $clearX = $lastX - 1;
                $clearY = $lastY - 1;
                echo "\033[{$clearY};{$clearX}H   ";
                echo "\033[" . ($clearY + 1) . ";{$clearX}H   ";
                echo "\033[" . ($clearY + 2) . ";{$clearX}H   ";
            }

            // Draw new cursor position with current color
            drawCursor($x, $y, $colors[$colorIndex]);

            // If dragging, draw path
            if ($isDragging) {
                $path[] = [$x, $y];
                // Draw dots for the entire path
                foreach ($path as $point) {
                    drawDot($point[0], $point[1], $colors[$colorIndex]);
                }
            }

            $lastX = $x;
            $lastY = $y;
        }
    }

    // Small sleep to prevent CPU spinning
    usleep(50000); // 50ms
}
