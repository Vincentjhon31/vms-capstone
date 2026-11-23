<?php
/**
 * The live queue strip, shown on every public page.
 *
 * Someone who has registered spends the next twenty minutes wondering whether
 * they have been called. Until now the only answer was to walk back to the
 * counter and ask, or to reload the tracking page - so the answer follows them
 * around the site instead: whichever page they are reading, the strip says who
 * is being served and how many are still ahead.
 *
 * It is fixed to the bottom rather than placed in the flow because the landing
 * hero is sized against the viewport, and a band inserted above it would push
 * the fold. Rendered server-side with today's real numbers so it is correct
 * before any script runs; js/queue.js keeps it current after that.
 */

$queue = queue_state();
?>
<aside class="queue-strip" id="queueStrip" aria-label="Queue status" data-queue-strip>
  <div class="wrap queue-strip__inner">
    <p class="queue-strip__now">
      <span class="queue-strip__pulse" aria-hidden="true"></span>
      <span class="queue-strip__label">Now serving</span>
      <strong class="queue-strip__value" data-queue-serving><?= $queue['serving'] ?? '--' ?></strong>
    </p>

    <p class="queue-strip__next">
      <span class="queue-strip__label">Next</span>
      <strong class="queue-strip__value" data-queue-next><?= $queue['next'] ?? '--' ?></strong>
    </p>

    <p class="queue-strip__waiting">
      <span data-queue-waiting><?= (int) $queue['waiting'] ?></span>
      <span data-queue-waiting-word><?= $queue['waiting'] === 1 ? 'person' : 'people' ?></span> waiting
    </p>

    <?php /* Filled in by the script only when this device holds a reference
             number from today - the server has no idea whose browser this is. */ ?>
    <p class="queue-strip__you" data-queue-you hidden></p>

    <a class="queue-strip__link" href="<?= url('TrackSubmission') ?>">
      <?= icon('search', 15) ?><span>Check my status</span>
    </a>
  </div>
</aside>
