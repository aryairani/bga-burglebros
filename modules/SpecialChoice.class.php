<?php

/*
 * SpecialChoice: values of the 'specialChoice' game state value — why the specialChoice
 * state was entered (keys of $special_choices in BurgleBrosMaterial)
 */
enum SpecialChoice: int
{
    case None = 0;
    case Rook1 = 1;
    case ClosestAlarm = 2;
}
