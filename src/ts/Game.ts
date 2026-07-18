// TypeScript port of burglebros.js — NOT YET DEPLOYED.
// The live game still runs the hand-written burglebros.js; this builds to
// build/burglebros.js (see rollup.config.mjs) until the port is complete.
import { BBCONST } from './constants';

export class Game {
    public bga: Bga<BurgleBrosPlayer, BurgleBrosGamedatas>;
    private gamedatas: BurgleBrosGamedatas;

    constructor(bga: Bga<BurgleBrosPlayer, BurgleBrosGamedatas>) {
        this.bga = bga;
    }

    public setup(gamedatas: BurgleBrosGamedatas) {
        console.log('Starting game setup', BBCONST.NOTIF.Message);
        this.gamedatas = gamedatas;
        this.setupNotifications();
    }

    public setupNotifications() {
        this.bga.notifications.setupPromiseNotifications();
    }
}
