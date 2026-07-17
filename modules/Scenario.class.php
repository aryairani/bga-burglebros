<?php

/*
 * Scenario: values of the Scenario game option (id 102; see gameoptions.json).
 * Stored in the 'scenario' game state value.
 */
enum Scenario: int
{
    case BankJob = 1;
    case OfficeJob = 2;
    case FortKnox = 3;
}
